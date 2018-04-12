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
use Contao\Module;
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

        return parent::generate();
    }

    /**
     * Generate the module
     */
    protected function compile()
    {
        $this->Template->items = $this->items;
    }

    /**
     * Get the feed items from cache
     *
     * @return array
     */
    private function getFeedItems()
    {
        $cacheFile = TL_ROOT . '/system/tmp/' . sprintf('%s.json', substr(md5($this->cfg_instagramAccessToken), 0, 8));
        $expires = time() - $this->rss_cache;

        if (!is_file($cacheFile) || (filemtime($cacheFile) < $expires)) {
            file_put_contents($cacheFile, json_encode($this->fetchFeedItems()));
        }

        return json_decode(file_get_contents($cacheFile), true);
    }

    /**
     * Fetch the feed items from Instagram
     *
     * @return array
     */
    private function fetchFeedItems()
    {
        $response = $this->sendRequest(sprintf('https://api.instagram.com/v1/users/self/media/recent', $userId), ['count' => $this->numberOfItems]);

        if ($response === null) {
            return [];
        }

        return $response['data'];
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
