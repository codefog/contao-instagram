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
$GLOBALS['TL_DCA']['tl_module']['fields']['numberOfItems']['load_callback'][] = ['Codefog\Instagram\EventListener\ModuleListener', 'loadCallbackNumberOfItems'];

/**
 * Add palettes
 */
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['cfg_instagramEndpoint_user'] = '';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['cfg_instagramEndpoint_tag'] = 'cfg_instagramTag';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['cfg_instagramStoreFiles'] = 'cfg_instagramStoreFolder,imgSize';

$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = 'cfg_instagramEndpoint';
$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = 'cfg_instagramStoreFiles';
$GLOBALS['TL_DCA']['tl_module']['palettes']['cfg_instagram'] = '{title_legend},name,headline,type;{config_legend},cfg_instagramClientId,cfg_instagramClientSecret,cfg_instagramAccessToken,cfg_instagramRequestToken,numberOfItems,rss_cache,cfg_instagramEndpoint,cfg_instagramStoreFiles;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID,space';

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

$GLOBALS['TL_DCA']['tl_module']['fields']['cfg_instagramEndpoint'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['cfg_instagramEndpoint'],
    'default' => 'user',
    'exclude' => true,
    'inputType' => 'radio',
    'options' => ['user', 'tag'],
    'reference' => &$GLOBALS['TL_LANG']['tl_module']['cfg_instagramEndpointRef'],
    'eval' => ['submitOnChange' => true, 'tl_class' => 'clr'],
    'sql' => "varchar(4) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['cfg_instagramTag'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['cfg_instagramTag'],
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'clr'],
    'sql' => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['cfg_instagramStoreFiles'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['cfg_instagramStoreFiles'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['submitOnChange' => true, 'tl_class' => 'clr'],
    'sql' => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['cfg_instagramStoreFolder'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['cfg_instagramStoreFolder'],
    'exclude' => true,
    'inputType' => 'fileTree',
    'eval' => ['mandatory' => true, 'fieldType' => 'radio', 'tl_class' => 'clr'],
    'sql' => "binary(16) NULL",
];
