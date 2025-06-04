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
use Contao\File;
use Contao\FilesModel;
use Contao\StringUtil;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Path;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class InstagramClient
{
    public function __construct(
        private readonly CacheInterface $appCache,
        private readonly ContaoFramework $framework,
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $contaoLogger,
        private readonly int $cacheTtl,
        private readonly string $projectDir,
    )
    {
    }

    /**
     * Get the data from Instagram.
     */
    public function getData(string $url, array $query = [], int $moduleId = null, bool $cache = true, bool $skipSslVerification = false): ?array
    {
        $cacheKey = md5($url . '_' . ($moduleId ?? '0'));

        if (!$cache) {
            $this->appCache->delete($cacheKey);
        }

        return $this->appCache->get($cacheKey, function (ItemInterface $item) use ($url, $query, $skipSslVerification) {
            $item->expiresAfter($this->cacheTtl);

            try {
                return $this->httpClient->request('GET', $url, ['query' => $query, 'verify_host' => !$skipSslVerification, 'verify_peer' => !$skipSslVerification])->toArray();
            } catch (TransportExceptionInterface | HttpExceptionInterface $e) {
                $this->contaoLogger->error(sprintf('Unable to fetch Instagram data from "%s": %s', $url, $e->getMessage()));
                $item->expiresAfter(0);

                return null;
            }
        });
    }

    /**
     * Get the media data.
     */
    public function getMediaData(string $accessToken, int $moduleId = null, bool $cache = true, bool $skipSslVerification = false): ?array
    {
        return $this->getData('https://graph.instagram.com/me/media', [
            'access_token' => $accessToken,
            'fields' => 'id,caption,media_type,media_url,like_count,permalink,thumbnail_url,timestamp',
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
     * Get the Comments for a Media Item
     */
    public function getCommentsForMedia(string $instagramAccessToken, string $mediaId, int $moduleId = null, bool $cache = true, bool $skipSslVerification = false): ?array
    {
        return $this->getData(sprintf('https://graph.instagram.com/%s/comments', $mediaId), [
            'access_token' => $instagramAccessToken,
            'fields' => 'id,text,timestamp',
        ], $moduleId, $cache, $skipSslVerification);
    }

    /**
     * Get Details for a Comment
     */
    public function getDetailsForComment(string $instagramAccessToken, string $commentId, int $moduleId = null, bool $cache = true, bool $skipSslVerification = false): ?array
    {
        return $this->getData( sprintf('https://graph.instagram.com/%s', $commentId), [
            'access_token' => $instagramAccessToken,
            'fields' => 'id,parent_id,from,text,like_count,hidden,timestamp',
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

        if (null === ($folderModel = FilesModel::findByPk($targetUuid)) || !is_dir(Path::join($this->projectDir, $folderModel->path))) {
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
                    $response = $this->httpClient->request('GET', $url, [
                        'verify_host' => !$skipSslVerification,
                        'verify_peer' => !$skipSslVerification,
                    ])->getContent();
                } catch (TransportExceptionInterface | HttpExceptionInterface $e) {
                    $this->contaoLogger->error(sprintf('Unable to fetch Instagram image from "%s": %s', $url, $e->getMessage()));

                    continue;
                }

                // Save the image and add sync the database
                $file->write($response);
                $file->close();
            }

            // Store the UUID in cache
            if ($file->exists() && ($uuid = $file->getModel()?->uuid)) {
                $item['contao']['uuid'] = StringUtil::binToUuid($uuid);
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
            $response = $this->httpClient->request('GET', 'https://graph.instagram.com/refresh_access_token', [
                'query' => [
                    'grant_type' => 'ig_refresh_token',
                    'access_token' => $token,
                ],
            ])->toArray();
        } catch (TransportExceptionInterface | HttpExceptionInterface $e) {
            $this->contaoLogger->error(sprintf('Unable to refresh the Instagram access token: %s', $e->getMessage()));

            return null;
        }

        return $response['access_token'];
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
     */
    private function getShortLivedAccessToken(string $appId, string $appSecret, string $code, string $redirectUri, bool $skipSslVerification = false): ?string
    {
        try {
            $response = $this->httpClient->request('POST', 'https://api.instagram.com/oauth/access_token', [
                'verify_host' => !$skipSslVerification,
                'verify_peer' => !$skipSslVerification,
                'body' => [
                    'app_id' => $appId,
                    'app_secret' => $appSecret,
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => $redirectUri,
                    'code' => $code,
                ],
            ])->toArray();
        } catch (TransportExceptionInterface | HttpExceptionInterface $e) {
            $this->contaoLogger->error(sprintf('Unable to fetch the Instagram short-lived access token: %s', $e->getMessage()));

            return null;
        }

        return $response['access_token'];
    }

    /**
     * Get the long lived access token
     */
    private function getLongLivedAccessToken(string $token, string $appSecret, bool $skipSslVerification = false): ?string
    {
        try {
            $response = $this->httpClient->request('GET', 'https://graph.instagram.com/access_token', [
                'verify_host' => !$skipSslVerification,
                'verify_peer' => !$skipSslVerification,
                'query' => [
                    'grant_type' => 'ig_exchange_token',
                    'client_secret' => $appSecret,
                    'access_token' => $token,
                ],
            ])->toArray();
        } catch (TransportExceptionInterface | HttpExceptionInterface $e) {
            $this->contaoLogger->error(sprintf('Unable to fetch the Instagram long-lived access token: %s', $e->getMessage()));

            return null;
        }

        return $response['access_token'];
    }
}
