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

use Symfony\Component\Filesystem\Filesystem;

class InstagramRequestCache
{
    /**
     * @var Filesystem
     */
    private $fs;

    /**
     * @var string
     */
    private $projectDir;

    /**
     * FeedRequestCache constructor.
     */
    public function __construct(Filesystem $fs, string $projectDir)
    {
        $this->fs = $fs;
        $this->projectDir = $projectDir;
    }

    /**
     * Get the cache dir.
     */
    public function getCacheDir(int $moduleId = null): ?string
    {
        return $this->projectDir.'/var/cache/instagram/'.($moduleId ?? '_');
    }

    /**
     * Get the cache TTL.
     */
    public function getCacheTtl(): int
    {
        return 84600 * 365; // 1 year
    }

    /**
     * Purge the cache dir.
     */
    public function purge(string $dir): void
    {
        $this->fs->remove($dir);
    }
}
