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

namespace Codefog\Instagram\EventListener;

use Contao\Controller;
use Contao\DataContainer;
use Contao\Environment;
use Contao\Input;
use Contao\ModuleModel;
use Contao\System;
use Haste\Util\Url;

class ModuleListener
{
    /**
     * On load callback
     *
     * @param DataContainer $dc
     */
    public function onLoadCallback(DataContainer $dc = null)
    {
        if ($dc->id && Input::get('cfg_instagram') && ($code = Input::get('code'))) {
            $this->storeAccessToken($dc->id, $code);
        }
    }

    /**
     * On submit callback.
     *
     * @param DataContainer $dc
     */
    public function onSubmitCallback(DataContainer $dc)
    {
        if ($dc->activeRecord->type === 'cfg_instagram' && $dc->activeRecord->cfg_instagramClientId && Input::post('cfg_instagramRequestToken')) {
            $this->requestAccessToken($dc->activeRecord->cfg_instagramClientId);
        }
    }

    /**
     * An additional load_callback for field 'numberOfItems'
     *
     * @param               $varValue
     * @param DataContainer $dc
     */
    public function loadCallbackNumberOfItems($varValue, DataContainer $dc)
    {
        //  change alignment of the field in the backend for module type 'cfg_instagram'
        if ($dc->activeRecord->type === 'cfg_instagram')
        {
            $GLOBALS['TL_DCA']['tl_module']['fields']['numberOfItems']['eval']['tl_class'] .= ' clr';
        }
    }

    /**
     * On the request token save.
     *
     * @return null
     */
    public function onRequestTokenSave()
    {
        return null;
    }

    /**
     * Store the access token in the database
     *
     * @param int $id
     * @param string $code
     */
    private function storeAccessToken($id, $code)
    {
        if (($module = ModuleModel::findByPk($id)) === null) {
            return;
        }

        $data = [
            'client_id' => $module->cfg_instagramClientId,
            'client_secret' => $module->cfg_instagramClientSecret,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $_SESSION['CFG_INSTAGRAM_URI'],
            'code' => $code,
        ];

        $response = $this->sendRequest($data);

        if ($response === null || !$response['access_token']) {
            System::log(sprintf('Unable to fetch the Instagram access token: %s', $response), __METHOD__, TL_ERROR);
        } else {
            $module->cfg_instagramAccessToken = $response['access_token'];
            $module->save();
        }

        Controller::redirect(Url::removeQueryString(['cfg_instagram', 'code']));
    }

    /**
     * Send the request to Instagram
     *
     * @param array $data
     *
     * @return array|null
     */
    private function sendRequest(array $data)
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_URL => 'https://api.instagram.com/oauth/access_token',
        ]);

        $response = curl_exec($ch);

        curl_close($ch);

        return json_decode($response, true);
    }

    /**
     * Request the Instagram access token
     *
     * @param string $clientId
     */
    private function requestAccessToken($clientId)
    {
        $_SESSION['CFG_INSTAGRAM_URI'] = Environment::get('uri') . '&cfg_instagram=1';

        $data = [
            'client_id' => $clientId,
            'redirect_uri' => $_SESSION['CFG_INSTAGRAM_URI'],
            'response_type' => 'code',
            'scope' => 'public_content',
        ];

        Controller::redirect('https://api.instagram.com/oauth/authorize/?' . http_build_query($data));
    }
}
