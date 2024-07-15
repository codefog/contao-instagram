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

use Symfony\Component\HttpKernel\Bundle\Bundle;

class CodefogInstagramBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
