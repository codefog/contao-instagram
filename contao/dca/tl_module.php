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

/**
 * Add global callbacks.
 */
$GLOBALS['TL_DCA']['tl_module']['config']['onsubmit_callback'][] = [\Codefog\InstagramBundle\EventListener\ModuleListener::class, 'onSubmitCallback'];

/*
 * Add palettes
 */
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['cfg_instagramStoreFiles'] = 'cfg_instagramStoreFolder,imgSize';

$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = 'cfg_instagramStoreFiles';
$GLOBALS['TL_DCA']['tl_module']['palettes']['cfg_instagram'] = '{title_legend},name,headline,type;{config_legend},cfg_instagramAppId,cfg_instagramAppSecret,cfg_instagramAccessToken,cfg_instagramRequestToken,numberOfItems,cfg_skipSslVerification,cfg_instagramFetchComments,cfg_instagramMediaTypes,cfg_instagramStoreFiles;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID,space';

/*
 * Add fields
 */
$GLOBALS['TL_DCA']['tl_module']['fields']['cfg_instagramAccessTokenTstamp'] = [
    'eval' => ['rgxp' => 'datim'],
    'sql' => "int(10) unsigned NOT NULL default '0'",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['cfg_instagramAppId'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['cfg_instagramAppId'],
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
    'sql' => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['cfg_instagramAppSecret'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['cfg_instagramAppSecret'],
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
    'sql' => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['cfg_instagramAccessToken'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['cfg_instagramAccessToken'],
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['readonly' => false, 'tl_class' => 'w50'],
    'sql' => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['cfg_instagramRequestToken'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['cfg_instagramRequestToken'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['doNotSaveEmpty' => true, 'tl_class' => 'w50 m12'],
    'save_callback' => [
        [\Codefog\InstagramBundle\EventListener\ModuleListener::class, 'onRequestTokenSave'],
    ],
];

$GLOBALS['TL_DCA']['tl_module']['fields']['cfg_skipSslVerification'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['cfg_skipSslVerification'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50 m12'],
    'sql' => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['cfg_instagramFetchComments'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['cfg_instagramFetchComments'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'clr'],
    'sql' => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['cfg_instagramMediaTypes'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['cfg_instagramMediaTypes'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'options' => ['IMAGE', 'VIDEO', 'CAROUSEL_ALBUM'],
    'reference' => &$GLOBALS['TL_LANG']['tl_module']['cfg_instagramMediaTypesRef'],
    'eval' => ['mandatory' => true, 'multiple' => true, 'tl_class' => 'clr'],
    'sql' => ['type' => 'string', 'length' => 255, 'default' => 'a:3:{i:0;s:5:"IMAGE";i:1;s:5:"VIDEO";i:2;s:14:"CAROUSEL_ALBUM";}'],
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
    'sql' => 'binary(16) NULL',
];
