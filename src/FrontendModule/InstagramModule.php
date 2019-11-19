<?php

/*
 * Instagram Bundle for Contao Open Source CMS.
 *
 * Copyright (C) 2011-2019 Codefog
 *
 * @author  Codefog <https://codefog.pl>
 * @author  Kamil Kuzminski <https://github.com/qzminski>
 * @license MIT
 */

namespace Codefog\InstagramBundle\FrontendModule;

use Contao\BackendTemplate;
use Contao\Controller;
use Contao\File;
use Contao\FilesModel;
use Contao\Module;
use Contao\StringUtil;
use Contao\System;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use Patchwork\Utf8;

class InstagramModule extends Module
{
    /**
     * Template.
     *
     * @var string
     */
    protected $strTemplate = 'mod_cfg_instagram';

    /**
     * Items.
     *
     * @var array
     */
    protected $items = [];

    /**
     * Display a wildcard in the back end.
     *
     * @return string
     */
    public function generate()
    {
        if (TL_MODE === 'BE') {
            $template = new BackendTemplate('be_wildcard');

            $template->wildcard = '### '.Utf8::strtoupper($GLOBALS['TL_LANG']['FMD']['cfg_instagram'][0]).' ###';
            $template->title = $this->headline;
            $template->id = $this->id;
            $template->link = $this->name;
            $template->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id='.$this->id;

            return $template->parse();
        }

        if (!$this->cfg_instagramAccessToken || 0 === \count($this->items = $this->getFeedItems())) {
            return '';
        }

        // Backwards compatibility
        $this->cfg_instagramEndpoint = $this->cfg_instagramEndpoint ?: 'user';

        return parent::generate();
    }

    /**
     * Generate the module.
     */
    protected function compile()
    {
        $this->Template->items = $this->generateItems();
        $this->Template->user = $this->getUserData();
    }

    /**
     * Generate the items.
     *
     * @return array
     */
    protected function generateItems()
    {
        $items = $this->items;

        foreach ($items as &$item) {
            // Skip the items that are not local Contao files
            if (!isset($item['contao']['uuid'])
                || null === ($fileModel = FilesModel::findByPk($item['contao']['uuid']))
                || !is_file(TL_ROOT.'/'.$fileModel->path)
            ) {
                continue;
            }

            $helper = new \stdClass();
            Controller::addImageToTemplate($helper, ['singleSRC' => $fileModel->path, 'size' => $this->imgSize]);
            $item['contao']['picture'] = $helper;
        }

        return $items;
    }

    /**
     * Get the user data from Instagram.
     *
     * @return array
     */
    protected function getUserData()
    {
        $response = $this->sendRequest('https://graph.instagram.com/me/media', ['fields' => 'id,username']);

        if (null === $response) {
            return [];
        }

        return $response['data'];
    }

    /**
     * Get the feed items from Instagram.
     *
     * @return array
     */
    protected function getFeedItems()
    {
        $options = [];

        switch ($this->cfg_instagramEndpoint) {
            case 'user':
                $endpoint = 'https://graph.instagram.com/me/media';
                $options['fields'] = 'id,caption,media_type,media_url,permalink,timestamp';
                break;
            // @todo – remove finding by tag as it is not supported
            case 'tag':
                $endpoint = sprintf('https://api.instagram.com/v1/tags/%s/media/recent', $this->cfg_instagramTag);
                break;
            default:
                return [];
        }

        // Set the limit only if greater than zero (#10)
        if ($this->numberOfItems > 0) {
            $options['count'] = $this->numberOfItems;
        }

        $response = $this->sendRequest($endpoint, $options);

        if (null === $response) {
            return [];
        }

        $data = $response['data'];

        // Store the files locally
        if ($this->cfg_instagramStoreFiles) {
            $data = $this->storeMediaFiles($data);
        }

        return $data;
    }

    /**
     * Send and cache the request to Instagram.
     *
     * @param string $url
     *
     * @return array
     */
    protected function sendRequest($url, array $data = [])
    {
        $data['access_token'] = $this->cfg_instagramAccessToken;

        $cacheKey = substr(md5($url.'|'.implode('-', $data)), 0, 8);
        $cacheFile = TL_ROOT.'/system/tmp/'.sprintf('%s.json', $cacheKey);
        $expires = time() - $this->rss_cache;

        if (!is_file($cacheFile) || (filemtime($cacheFile) < $expires)) {
            file_put_contents($cacheFile, json_encode($this->executeRequest($url, $data)));
        }

        return json_decode(file_get_contents($cacheFile), true);
    }

    /**
     * Store the media files locally.
     *
     * @throws \RuntimeException
     *
     * @return array
     */
    private function storeMediaFiles(array $data)
    {
        if (null === ($folderModel = FilesModel::findByPk($this->cfg_instagramStoreFolder)) || !is_dir(TL_ROOT.'/'.$folderModel->path)) {
            throw new \RuntimeException('The target folder does not exist');
        }

        foreach ($data as &$item) {
            if ($item['media_type'] !== 'IMAGE') {
                continue;
            }

            $extension = pathinfo(explode('?', $item['media_url'])[0], PATHINFO_EXTENSION);
            $file = new File(sprintf('%s/%s.%s', $folderModel->path, $item['id'], $extension));

            // Download the image
            if (!$file->exists()) {
                try {
                    $response = (new Client())->get($item['media_url']);
                } catch (ClientException | ServerException $e) {
                    System::log(sprintf('Unable to fetch Instagram image from "%s": %s', $item['media_url'], $e->getMessage()), __METHOD__, TL_ERROR);

                    continue;
                }

                // Save the image and add sync the database
                $file->write($response->getBody());
                $file->close();
            }

            // Store the UUID in cache
            if ($file->exists()) {
                $item['contao']['uuid'] = StringUtil::binToUuid($file->getModel()->uuid);
            }
        }

        return $data;
    }

    /**
     * Execute the request to Instagram.
     *
     * @param string $url
     * @param array $query
     *
     * @return array|null
     */
    private function executeRequest($url, array $query = [])
    {
        try {
            $response = (new Client())->get($url, ['query' => $query]);
        } catch (ClientException | ServerException $e) {
            System::log(sprintf('Unable to fetch Instagram data from "%s": %s', $url, $e->getMessage()), __METHOD__, TL_ERROR);

            return null;
        }

        $data = json_decode($response->getBody(), true);

        if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
            System::log(sprintf('Unable to decode Instagram data from "%s": %s', $url, json_last_error_msg()), __METHOD__,TL_ERROR);

            return null;
        }

        return $data;
    }
}
