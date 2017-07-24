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

/**
 * Register PSR-0 namespace
 */
if (class_exists('NamespaceClassLoader')) {
    NamespaceClassLoader::add('Codefog\Instagram', 'system/modules/cfg_instagram/src');
}

/**
 * Register the templates
 */
TemplateLoader::addFiles(
    [
        'mod_cfg_instagram' => 'system/modules/cfg_instagram/templates/modules',
    ]
);

