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

namespace Codefog\InstagramBundle\EventListener;

use Contao\CoreBundle\Exception\RedirectResponseException;
use Contao\DataContainer;
use Contao\Environment;
use Contao\Input;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class ModuleListener
{
    public const SESSION_KEY = 'instagram-module-id';

    public function __construct(
        private readonly RouterInterface $router,
        private readonly RequestStack $requestStack,
    )
    {
    }

    /**
     * On submit callback.
     */
    public function onSubmitCallback(DataContainer $dc)
    {
        if ('cfg_instagram' === $dc->activeRecord->type && $dc->activeRecord->cfg_instagramAppId && Input::post('cfg_instagramRequestToken')) {
            $this->requestAccessToken($dc->activeRecord->cfg_instagramAppId);
        }
    }

    /**
     * On the request token save.
     */
    public function onRequestTokenSave()
    {
        return null;
    }

    /**
     * Request the Instagram access token.
     */
    private function requestAccessToken(string $clientId): void
    {
        $session = $this->requestStack->getSession();
        $session->set(self::SESSION_KEY, [
            'moduleId' => Input::get('id'),
            'backUrl' => Environment::get('uri'),
        ]);

        $session->save();

        $data = [
            'app_id' => $clientId,
            'redirect_uri' => $this->router->generate('instagram_auth', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'response_type' => 'code',
            'scope' => 'user_profile,user_media',
        ];

        throw new RedirectResponseException('https://api.instagram.com/oauth/authorize/?'.http_build_query($data));
    }
}
