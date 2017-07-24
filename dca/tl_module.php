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
 * Add global callbacks
 */
$GLOBALS['TL_DCA']['tl_module']['config']['onload_callback'][] = ['Codefog\Instagram\EventListener\ModuleListener', 'onLoadCallback'];
$GLOBALS['TL_DCA']['tl_module']['config']['onsubmit_callback'][] = ['Codefog\Instagram\EventListener\ModuleListener', 'onSubmitCallback'];

/**
 * Add palettes
 */
$GLOBALS['TL_DCA']['tl_module']['palettes']['cfg_instagram'] = '{title_legend},name,headline,type;{config_legend},cfg_instagramClientId,cfg_instagramClientSecret,cfg_instagramAccessToken,cfg_instagramRequestToken,cfg_instagramUser,numberOfItems,rss_cache;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID,space';

/**
 * Add fields
 */
$GLOBALS['TL_DCA']['tl_module']['fields']['cfg_instagramClientId'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['cfg_instagramClientId'],
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
    'sql' => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['cfg_instagramClientSecret'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['cfg_instagramClientSecret'],
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
    'sql' => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['cfg_instagramAccessToken'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['cfg_instagramAccessToken'],
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['readonly' => true, 'tl_class' => 'w50'],
    'sql' => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['cfg_instagramRequestToken'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['cfg_instagramRequestToken'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['doNotSaveEmpty' => true, 'tl_class' => 'w50 m12'],
    'save_callback' => [
        ['Codefog\Instagram\EventListener\ModuleListener', 'onRequestTokenSave']
    ]
];

$GLOBALS['TL_DCA']['tl_module']['fields']['cfg_instagramUser'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['cfg_instagramUser'],
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
    'sql' => "varchar(255) NOT NULL default ''",
];
