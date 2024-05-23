<?php

namespace Codefog\InstagramBundle\Cron;

use Codefog\InstagramBundle\InstagramClient;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Doctrine\DBAL\Connection;

#[AsCronJob('daily')]
class RefreshAccessTokenCron
{
    public function __construct(
        private readonly Connection $connection,
        private readonly InstagramClient $client,
    )
    {
    }

    public function __invoke(): void
    {
        $modules = $this->connection->fetchAllAssociative('SELECT id, cfg_instagramAccessToken FROM tl_module WHERE type=?', ['cfg_instagram']);

        foreach ($modules as $module) {
            $newToken = $this->client->refreshAccessToken($module['cfg_instagramAccessToken']);

            if ($newToken !== null) {
                $this->connection->update('tl_module', [
                    'cfg_instagramAccessToken' => $newToken,
                    'cfg_instagramAccessTokenTstamp' => time(),
                ], ['id' => $module['id']]);
            }
        }
    }
}
