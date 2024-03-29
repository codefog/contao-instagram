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

namespace Codefog\InstagramBundle;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\File;
use Contao\FilesModel;
use Contao\StringUtil;
use Doctrine\Common\Cache\FilesystemCache;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\HandlerStack;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Kevinrob\GuzzleCache\Storage\DoctrineCacheStorage;
use Kevinrob\GuzzleCache\Strategy\GreedyCacheStrategy;
use Psr\Log\LoggerInterface;

class InstagramClient
{
    /**
     * @var InstagramRequestCache
     */
    private $cache;

    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * InstagramClient constructor.
     */
    public function __construct(InstagramRequestCache $cache, ContaoFramework $framework, LoggerInterface $logger)
    {
        $this->cache = $cache;
        $this->framework = $framework;
        $this->logger = $logger;
    }

    /**
     * Get the data from Instagram.
     */
    public function getData(string $url, array $query = [], int $moduleId = null, bool $cache = true, bool $skipSslVerification = false): ?array
    {
        if (!$cache) {
            $this->cache->purge($this->cache->getCacheDir($moduleId));
        }

        try {
            $response = $this->getCachedClient($moduleId)->get($url, ['query' => $query, 'verify' => !$skipSslVerification]);
        } catch (ClientException | ConnectException | ServerException $e) {
            if (null !== $this->logger) {
                $this->logger->error(sprintf('Unable to fetch Instagram data from "%s": %s', $url, $e->getMessage()), ['contao' => new ContaoContext(__METHOD__, TL_ERROR)]);
            }

            return null;
        }

        $data = json_decode($response->getBody(), true);

        if (!\is_array($data) || JSON_ERROR_NONE !== json_last_error()) {
            if (null !== $this->logger) {
                $this->logger->error(sprintf('Unable to decode Instagram data from "%s": %s', $url, json_last_error_msg()), ['contao' => new ContaoContext(__METHOD__, TL_ERROR)]);
            }

            return null;
        }

        return $data;
    }

    /**
     * Get the media data.
     */
    public function getMediaData(string $accessToken, int $moduleId = null, bool $cache = true, bool $skipSslVerification = false): ?array
    {
        return $this->getData('https://graph.instagram.com/me/media', [
            'access_token' => $accessToken,
            'fields' => 'id,caption,media_type,media_url,permalink,thumbnail_url,timestamp',
        ], $moduleId, $cache, $skipSslVerification);
    }

    /**
     * Get the user data.
     */
    public function getUserData(string $accessToken, int $moduleId = null, bool $cache = true, bool $skipSslVerification = false): ?array
    {
        return $this->getData('https://graph.instagram.com/me', [
            'access_token' => $accessToken,
            'fields' => 'id,username',
        ], $moduleId, $cache, $skipSslVerification);
    }

    /**
     * Store the media files locally.
     *
     * @throws \RuntimeException
     */
    public function storeMediaFiles(string $targetUuid, array $data, bool $skipSslVerification = false): array
    {
        $this->framework->initialize();

        if (null === ($folderModel = FilesModel::findByPk($targetUuid)) || !is_dir(TL_ROOT.'/'.$folderModel->path)) {
            throw new \RuntimeException('The target folder does not exist');
        }

        // Support raw responses as well
        if (isset($data['data'])) {
            $data = $data['data'];
        }

        foreach ($data as &$item) {
            switch ($item['media_type']) {
                case 'IMAGE':
                case 'CAROUSEL_ALBUM':
                    $url = $item['media_url'];
                    break;
                case 'VIDEO':
                    $url = $item['thumbnail_url'];
                    break;
                default:
                    continue 2;
            }

            // Skip if the URL does not exist (#39)
            if (!$url) {
                continue;
            }

            $extension = pathinfo(explode('?', $url)[0], PATHINFO_EXTENSION);
            $file = new File(sprintf('%s/%s.%s', $folderModel->path, $item['id'], $extension));

            // Download the image
            if (!$file->exists()) {
                try {
                    $response = $this->getClient()->get($url, ['verify' => !$skipSslVerification]);
                } catch (ClientException | ConnectException | ServerException $e) {
                    if (null !== $this->logger) {
                        $this->logger->error(sprintf('Unable to fetch Instagram image from "%s": %s', $url, $e->getMessage()), ['contao' => new ContaoContext(__METHOD__, TL_ERROR)]);
                    }

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
     * Refresh the access token.
     */
    public function refreshAccessToken(string $token): ?string
    {
        try {
            $response = $this->getClient()->get('https://graph.instagram.com/refresh_access_token', [
                'query' => [
                    'grant_type' => 'ig_refresh_token',
                    'access_token' => $token,
                ],
            ]);
        } catch (ClientException | ConnectException | ServerException $e) {
            if (null !== $this->logger) {
                $this->logger->error(sprintf('Unable to refresh the Instagram access token: %s', $e->getMessage()), ['contao' => new ContaoContext(__METHOD__, TL_ERROR)]);
            }

            return null;
        }

        $data = json_decode($response->getBody(), true);

        if (!\is_array($data) || JSON_ERROR_NONE !== json_last_error()) {
            if (null !== $this->logger) {
                $this->logger->error(sprintf('Unable to refresh the Instagram access token: %s', json_last_error_msg()), ['contao' => new ContaoContext(__METHOD__, TL_ERROR)]);
            }

            return null;
        }

        return $data['access_token'];
    }

    /**
     * Get the access token.
     */
    public function getAccessToken(string $appId, string $appSecret, string $code, string $redirectUri, bool $skipSslVerification = false): ?string
    {
        if (($token = $this->getShortLivedAccessToken($appId, $appSecret, $code, $redirectUri, $skipSslVerification)) === null) {
            return null;
        }

        return $this->getLongLivedAccessToken($token, $appSecret, $skipSslVerification);
    }

    /**
     * Get the short lived access token
     *
     * @return string
     */
    private function getShortLivedAccessToken(string $appId, string $appSecret, string $code, string $redirectUri, bool $skipSslVerification = false): ?string
    {
        try {
            $response = $this->getClient()->post('https://api.instagram.com/oauth/access_token', [
                'verify' => !$skipSslVerification,
                'form_params' => [
                    'app_id' => $appId,
                    'app_secret' => $appSecret,
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => $redirectUri,
                    'code' => $code,
                ],
            ]);
        } catch (ClientException | ConnectException | ServerException $e) {
            if (null !== $this->logger) {
                $this->logger->error(sprintf('Unable to fetch the Instagram short-lived access token: %s', $e->getMessage()), ['contao' => new ContaoContext(__METHOD__, TL_ERROR)]);
            }

            return null;
        }

        $data = json_decode($response->getBody(), true);

        if (!\is_array($data) || JSON_ERROR_NONE !== json_last_error()) {
            if (null !== $this->logger) {
                $this->logger->error(sprintf('Unable to fetch the Instagram short-lived access token: %s', json_last_error_msg()), ['contao' => new ContaoContext(__METHOD__, TL_ERROR)]);
            }

            return null;
        }

        return $data['access_token'];
    }

    /**
     * Get the long lived access token
     *
     * @return string
     */
    private function getLongLivedAccessToken(string $token, string $appSecret, bool $skipSslVerification = false): ?string
    {
        try {
            $response = $this->getClient()->get('https://graph.instagram.com/access_token', [
                'verify' => !$skipSslVerification,
                'query' => [
                    'grant_type' => 'ig_exchange_token',
                    'client_secret' => $appSecret,
                    'access_token' => $token,
                ],
            ]);
        } catch (ClientException | ConnectException | ServerException $e) {
            if (null !== $this->logger) {
                $this->logger->error(sprintf('Unable to fetch the Instagram long-lived access token: %s', $e->getMessage()), ['contao' => new ContaoContext(__METHOD__, TL_ERROR)]);
            }

            return null;
        }

        $data = json_decode($response->getBody(), true);

        if (!\is_array($data) || JSON_ERROR_NONE !== json_last_error()) {
            if (null !== $this->logger) {
                $this->logger->error(sprintf('Unable to fetch the Instagram long-lived access token: %s', json_last_error_msg()), ['contao' => new ContaoContext(__METHOD__, TL_ERROR)]);
            }

            return null;
        }

        return $data['access_token'];
    }

    /**
     * Get the client.
     */
    private function getClient(): Client
    {
        static $client;

        if (null === $client) {
            $client = new Client();
        }

        return $client;
    }

    /**
     * Get the cache client.
     */
    private function getCachedClient(int $moduleId = null): Client
    {
        static $clients = [];

        $key = $moduleId ?? '_';

        if (!isset($clients[$key])) {
            $stack = HandlerStack::create();
            $stack->push(new CacheMiddleware(new GreedyCacheStrategy(new DoctrineCacheStorage(new FilesystemCache($this->cache->getCacheDir($moduleId))), $this->cache->getCacheTtl())), 'cache');

            $clients[$key] = (new Client(['handler' => $stack]));
        }

        return $clients[$key];
    }
}
