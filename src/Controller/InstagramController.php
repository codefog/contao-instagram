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
use Contao\BackendUser;
use Contao\CoreBundle\Monolog\ContaoContext;
use Doctrine\DBAL\Connection;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * @Route("/_instagram", defaults={"_scope" = "backend", "_token_check" = false})
 */
class InstagramController
{
    /**
     * @var Connection
     */
    private $db;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var SessionInterface
     */
    private $session;

    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    /**
     * @var LoggerInterface|null
     */
    private $logger;

    /**
     * InstagramController constructor.
     */
    public function __construct(Connection $db, RouterInterface $router, SessionInterface $session, TokenStorageInterface $tokenStorage, ?LoggerInterface $logger)
    {
        $this->db = $db;
        $this->router = $router;
        $this->session = $session;
        $this->tokenStorage = $tokenStorage;
        $this->logger = $logger;
    }

    /**
     * @Route("/auth", name="instagram_auth", methods={"GET"})
     */
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

        // Module ID not found in session
        if (!($moduleId = $this->session->get(ModuleListener::SESSION_KEY))) {
            return new Response(Response::$statusTexts[Response::HTTP_BAD_REQUEST], Response::HTTP_BAD_REQUEST);
        }

        // Module not found
        if (false === ($module = $this->db->fetchAssoc('SELECT * FROM tl_module WHERE id=?', [$moduleId]))) {
            return new Response(Response::$statusTexts[Response::HTTP_BAD_REQUEST], Response::HTTP_BAD_REQUEST);
        }

        $accessToken = $this->getAccessToken($module, $code);

        if ($accessToken === null) {
            return new Response(Response::$statusTexts[Response::HTTP_INTERNAL_SERVER_ERROR], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $this->db->update('tl_module', ['cfg_instagramAccessToken' => $accessToken], ['id' => $moduleId]);

        return new RedirectResponse($this->router->generate('contao_backend', [
            'do' => 'themes',
            'table' => 'tl_module',
            'id' => $moduleId,
            'act' => 'edit',
//            'ref' => '', // @todo
//            'rt' => '', // @todo
        ]));
    }

    /**
     * Get the access token data
     *
     * @param array $module
     * @param string $code
     *
     * @return string|null
     */
    private function getAccessToken(array $module, string $code): ?string
    {
        try {
            $response = (new Client())->post('https://api.instagram.com/oauth/access_token', [
                'app_id' => $module['cfg_instagramAppId'],
                'app_secret' => $module['cfg_instagramAppSecret'],
                'grant_type' => 'authorization_code',
                'redirect_uri' => $this->router->generate('instagram_auth', [], RouterInterface::ABSOLUTE_URL),
                'code' => $code,
            ]);
        } catch (ClientException | ServerException $e) {
            $this->logger->error(sprintf('Unable to fetch the Instagram access token: %s', $e->getMessage()), ['contao' => new ContaoContext(__METHOD__, TL_ERROR)]);

            return null;
        }

        $data = json_decode($response->getBody(), true);

        if (!\is_array($data) || JSON_ERROR_NONE !== json_last_error()) {
            $this->logger->error(sprintf('Unable to fetch the Instagram access token: %s', json_last_error_msg()), ['contao' => new ContaoContext(__METHOD__, TL_ERROR)]);

            return null;
        }

        return $data['access_token'];
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
