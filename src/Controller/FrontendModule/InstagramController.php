<?php

namespace Codefog\InstagramBundle\Controller\FrontendModule;

use Codefog\InstagramBundle\InstagramClient;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Image\Studio\Studio;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\FilesModel;
use Contao\ModuleModel;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[AsFrontendModule('cfg_instagram', category: 'application', template: 'mod_cfg_instagram')]
class InstagramController extends AbstractFrontendModuleController
{
    public function __construct(
        private readonly Connection $connection,
        private readonly InstagramClient $client,
        private readonly Studio $studio,
        private readonly int $accessTokenTtl,
        private readonly string $projectDir,
    )
    {
    }

    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        if (!$model->cfg_instagramAccessToken || 0 === \count($items = $this->getFeedItems($model))) {
            return new Response();
        }

        // Fetch comments for the media items
        foreach ($items as &$item) {
            if (isset($item['id'])) {
                $item['comments'] = $this->getCommentsForMedia($model->cfg_instagramAccessToken, $item['id']);
            }
        }

        $template->items = $this->generateItems($model, $items);
        $template->user = $this->getUserData($model);

        return $template->getResponse();
    }

    /**
     * Generate the items.
     */
    protected function generateItems(ModuleModel $moduleModel, array $items): array
    {
        foreach ($items as &$item) {
            // Skip the items that are not local Contao files
            if (!isset($item['contao']['uuid'])
                || null === ($fileModel = FilesModel::findByPk($item['contao']['uuid']))
                || !is_file(Path::join($this->projectDir, $fileModel->path))
            ) {
                continue;
            }

            $figure = $this->studio
                ->createFigureBuilder()
                ->from($fileModel)
                ->setSize($moduleModel->imgSize)
                ->buildIfResourceExists();

            if (null !== $figure) {
                $figure->applyLegacyTemplateData($item['contao']['picture'] = new \stdClass());
            }
        }

        return $items;
    }

    /**
     * Get the user data from Instagram.
     */
    protected function getUserData(ModuleModel $moduleModel): array
    {
        $response = $this->client->getUserData($moduleModel->cfg_instagramAccessToken, (int) $moduleModel->id, true, (bool) $moduleModel->cfg_skipSslVerification);

        if (null === $response) {
            return [];
        }

        return $response;
    }

    /**
     * Get the feed items from Instagram.
     */
    protected function getFeedItems(ModuleModel $moduleModel): array
    {
        $time = time();

        // Refresh the token if it expired (according to local TTL value)
        if (($time - $this->accessTokenTtl) > $moduleModel->cfg_instagramAccessTokenTstamp && ($token = $this->client->refreshAccessToken($moduleModel->cfg_instagramAccessToken)) !== null) {
            $moduleModel->cfg_instagramAccessToken = $token;
            $moduleModel->cfg_instagramAccessTokenTstamp = $time;

            $this->connection->update('tl_module', ['cfg_instagramAccessToken' => $token, 'cfg_instagramAccessTokenTstamp' => $time], ['id' => $moduleModel->id]);
        }

        $response = $this->client->getMediaData($moduleModel->cfg_instagramAccessToken, (int) $moduleModel->id, true, (bool) $moduleModel->cfg_skipSslVerification);

        if (empty($response['data'])) {
            return [];
        }

        $data = $response['data'];
        $allowedMediaTypes = StringUtil::deserialize($moduleModel->cfg_instagramMediaTypes);

        // Filter out the media types we don't want
        if (is_array($allowedMediaTypes) && !empty($allowedMediaTypes)) {
            $data = array_filter($data, static fn ($item) => in_array($item['media_type'], $allowedMediaTypes, true));
            $data = array_values($data);
        }

        // Store the files locally
        if ($moduleModel->cfg_instagramStoreFiles) {
            $data = $this->client->storeMediaFiles($moduleModel->cfg_instagramStoreFolder, $data, (bool) $moduleModel->cfg_skipSslVerification);
        }

        // Limit the number of items
        if ($moduleModel->numberOfItems > 0) {
            $data = \array_slice($data, 0, $moduleModel->numberOfItems);
        }

        return $data;
    }

    /**
     * Get comments for a media item.
     */
    protected function getCommentsForMedia(string $instagramAccessToken, string $mediaId): array
    {
        $response = $this->client->getCommentsForMedia($instagramAccessToken, $mediaId, false);

        if (empty($response['data'])) {
            return [];
        }

        foreach ($response['data']  as &$comment ) {
            $comment = $this->client->getDetailsForComment($instagramAccessToken, $comment['id']);
        }
        
        return $response['data'];
    }
}