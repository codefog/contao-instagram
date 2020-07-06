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

use Codefog\InstagramBundle\InstagramClient;
use Contao\BackendTemplate;
use Contao\Controller;
use Contao\FilesModel;
use Contao\Module;
use Contao\System;
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
     * @var InstagramClient
     */
    protected $client;

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

        $this->client = System::getContainer()->get(InstagramClient::class);

        if (!$this->cfg_instagramAccessToken || 0 === \count($this->items = $this->getFeedItems())) {
            return '';
        }

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
     */
    protected function generateItems(): array
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
     */
    protected function getUserData(): array
    {
        $response = $this->client->getUserData($this->cfg_instagramAccessToken, (int) $this->id, true, (bool) $this->cfg_skipSslVerification);

        if (null === $response) {
            return [];
        }

        return $response;
    }

    /**
     * Get the feed items from Instagram.
     */
    protected function getFeedItems(): array
    {
        if (($token = $this->client->refreshAccessToken($this->cfg_instagramAccessToken)) !== null) {
            $this->cfg_instagramAccessToken = $token;
            $this->objModel->cfg_instagramAccessToken = $token;
            $this->objModel->save();
        }

        $response = $this->client->getMediaData($this->cfg_instagramAccessToken, (int) $this->id, true, (bool) $this->cfg_skipSslVerification);

        if (null === $response) {
            return [];
        }

        $data = $response['data'];

        // Store the files locally
        if ($this->cfg_instagramStoreFiles) {
            $data = $this->client->storeMediaFiles($this->cfg_instagramStoreFolder, $data, (bool) $this->cfg_skipSslVerification);
        }

        // Limit the number of items
        if ($this->numberOfItems > 0) {
            $data = \array_slice($data, 0, $this->numberOfItems);
        }

        return $data;
    }
}
