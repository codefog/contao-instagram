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

namespace Codefog\InstagramBundle\Controller;

use Codefog\InstagramBundle\EventListener\ModuleListener;
use Codefog\InstagramBundle\InstagramClient;
use Contao\BackendUser;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[Route('_instagram', defaults: ['_scope' => 'backend', '_token_check' => false])]
class InstagramController
{
    public function __construct(
        private readonly InstagramClient $client,
        private readonly Connection $connection,
        private readonly RequestStack $requestStack,
        private readonly RouterInterface $router,
        private readonly TokenStorageInterface $tokenStorage,
    )
    {
    }

    #[Route('/auth', name: 'instagram_auth', methods: ['GET'])]
    public function authAction(Request $request): Response
    {
        // Missing code query parameter
        if (!($code = $request->query->get('code'))) {
            return new Response(Response::$statusTexts[Response::HTTP_BAD_REQUEST], Response::HTTP_BAD_REQUEST);
        }

        // User not logged in
        if (null === ($user = $this->getBackendUser())) {
            return new Response(Response::$statusTexts[Response::HTTP_UNAUTHORIZED], Response::HTTP_UNAUTHORIZED);
        }

        $sessionData = $this->requestStack->getSession()->get(ModuleListener::SESSION_KEY);

        // Module ID not found in session
        if (null === $sessionData || !isset($sessionData['moduleId'])) {
            return new Response(Response::$statusTexts[Response::HTTP_BAD_REQUEST], Response::HTTP_BAD_REQUEST);
        }

        // Module not found
        if (false === ($module = $this->connection->fetchAssociative('SELECT * FROM tl_module WHERE id=?', [$sessionData['moduleId']]))) {
            return new Response(Response::$statusTexts[Response::HTTP_BAD_REQUEST], Response::HTTP_BAD_REQUEST);
        }

        $accessToken = $this->client->getAccessToken(
            $module['cfg_instagramAppId'],
            $module['cfg_instagramAppSecret'],
            $code,
            $this->router->generate('instagram_auth', [], UrlGeneratorInterface::ABSOLUTE_URL),
            (bool) $module['cfg_skipSslVerification']
        );

        if (null === $accessToken) {
            return new Response(Response::$statusTexts[Response::HTTP_INTERNAL_SERVER_ERROR], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // Get the user and media data
        $this->client->getUserData($accessToken, (int) $module['id'], false, (bool) $module['cfg_skipSslVerification']);
        $mediaData = $this->client->getMediaData($accessToken, (int) $module['id'], false, (bool) $module['cfg_skipSslVerification']);

        // Optionally store the media data locally
        if ($module['cfg_instagramStoreFiles'] && null !== $mediaData) {
            $this->client->storeMediaFiles($module['cfg_instagramStoreFolder'], $mediaData, (bool) $module['cfg_skipSslVerification']);
        }

        // Store the access token and remove temporary session key
        $this->connection->update('tl_module', ['cfg_instagramAccessToken' => $accessToken], ['id' => $sessionData['moduleId']]);
        $this->requestStack->getSession()->remove(ModuleListener::SESSION_KEY);

        return new RedirectResponse($sessionData['backUrl']);
    }

    /**
     * Get the backend user.
     */
    private function getBackendUser(): ?BackendUser
    {
        if (null === ($token = $this->tokenStorage->getToken())) {
            return null;
        }

        $user = $token->getUser();

        if (!($user instanceof BackendUser)) {
            return null;
        }

        return $user;
    }
}
