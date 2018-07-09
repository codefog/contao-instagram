<?php

/**
 * Instagram extension for Contao Open Source CMS
 *
 * Copyright (C) 2011-2017 Codefog
 *
 * @author  Codefog <https://codefog.pl>
 * @author  Kamil Kuzminski <https://github.com/qzminski>
 * @license MIT
 */

namespace Codefog\Instagram\FrontendModule;

use Contao\BackendTemplate;
use Contao\Controller;
use Contao\File;
use Contao\FilesModel;
use Contao\Module;
use Contao\StringUtil;
use Contao\System;

class InstagramModule extends Module
{
    /**
     * Template
     * @var string
     */
    protected $strTemplate = 'mod_cfg_instagram';

    /**
     * Items
     * @var array
     */
    protected $items = [];

    /**
     * Display a wildcard in the back end
     *
     * @return string
     */
    public function generate()
    {
        if (TL_MODE === 'BE') {
            $template = new BackendTemplate('be_wildcard');

            $template->wildcard = '### ' . utf8_strtoupper($GLOBALS['TL_LANG']['FMD']['cfg_instagram'][0]) . ' ###';
            $template->title = $this->headline;
            $template->id = $this->id;
            $template->link = $this->name;
            $template->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id=' . $this->id;

            return $template->parse();
        }

        if (!$this->cfg_instagramAccessToken || count($this->items = $this->getFeedItems()) === 0) {
            return '';
        }

        // Backwards compatibility
        $this->cfg_instagramEndpoint = $this->cfg_instagramEndpoint ?: 'user';

        return parent::generate();
    }

    /**
     * Generate the module
     */
    protected function compile()
    {
        $this->Template->items = $this->generateItems();
    }

    /**
     * Generate the items
     *
     * @return array
     */
    protected function generateItems()
    {
        $items = $this->items;

        foreach ($items as &$item) {
            // Skip the items that are not local Contao files
            if (!isset($item['contao']['uuid'])
                || ($fileModel = FilesModel::findByPk($item['contao']['uuid'])) === null
                || !is_file(TL_ROOT . '/'. $fileModel->path)
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
     * Get the cache key
     *
     * @return string
     */
    protected function getCacheKey()
    {
        $chunks = [$this->cfg_instagramAccessToken, $this->cfg_instagramEndpoint, $this->numberOfItems];

        if ($this->cfg_instagramEndpoint === 'tag') {
            $chunks[] = $this->cfg_instagramTag;
        }

        return substr(md5(implode('-', $chunks)), 0, 8);
    }

    /**
     * Fetch the feed items from Instagram
     *
     * @return array
     */
    protected function fetchFeedItems()
    {
        switch ($this->cfg_instagramEndpoint) {
            case 'user':
                $endpoint = 'https://api.instagram.com/v1/users/self/media/recent';
                break;
            case 'tag':
                $endpoint = sprintf('https://api.instagram.com/v1/tags/%s/media/recent', $this->cfg_instagramTag);
                break;
            default:
                return [];
        }

        $response = $this->sendRequest($endpoint, ['count' => $this->numberOfItems]);

        if ($response === null) {
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
     * Store the media files locally
     *
     * @param array $data
     *
     * @return array
     *
     * @throws \RuntimeException
     */
    private function storeMediaFiles(array $data)
    {
        if (($folderModel = FilesModel::findByPk($this->cfg_instagramStoreFolder)) === null || !is_dir(TL_ROOT . '/' . $folderModel->path)) {
            throw new \RuntimeException('The target folder does not exist');
        }

        foreach ($data as &$item) {
            $url = $item['images']['standard_resolution']['url'];
            $extension = pathinfo(explode('?', $url)[0], PATHINFO_EXTENSION);
            $target = sprintf('%s/%s.%s', $folderModel->path, $item['id'], $extension);

            // Download the image
            $ch = curl_init();
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_URL => $url]);
            $response = curl_exec($ch);
            curl_close($ch);

            // Save the image and add sync the database
            if ($response !== false) {
                $file = new File($target);
                $file->write($response);
                $file->close();

                // Store the UUID in cache
                if ($file->exists()) {
                    $item['contao']['uuid'] = StringUtil::binToUuid($file->getModel()->uuid);
                }
            }
        }

        return $data;
    }

    /**
     * Get the feed items from cache
     *
     * @return array
     */
    private function getFeedItems()
    {
        $cacheFile = TL_ROOT . '/system/tmp/' . sprintf('%s.json', $this->getCacheKey());
        $expires = time() - $this->rss_cache;

        if (!is_file($cacheFile) || (filemtime($cacheFile) < $expires)) {
            file_put_contents($cacheFile, json_encode($this->fetchFeedItems()));
        }

        return json_decode(file_get_contents($cacheFile), true);
    }

    /**
     * Send the request to Instagram
     *
     * @param string $url
     * @param array  $data
     *
     * @return array|null
     */
    private function sendRequest($url, array $data)
    {
        $data['access_token'] = $this->cfg_instagramAccessToken;

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_URL => $url . '?' . http_build_query($data),
        ]);

        $response = json_decode(curl_exec($ch), true);

        curl_close($ch);

        if ($response === null || !$response['data'] || $response['meta']['code'] !== 200) {
            System::log(
                sprintf('Unable to fetch Instagram data: %s, %s, %s', $url, print_r($data, true), $response),
                __METHOD__,
                TL_ERROR
            );

            return null;
        }

        return $response;
    }
}
