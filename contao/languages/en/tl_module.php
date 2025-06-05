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
 * Fields.
 */
$GLOBALS['TL_LANG']['tl_module']['cfg_instagramAppId'] = ['Instagram App ID', 'Please enter the Instagram App ID.'];
$GLOBALS['TL_LANG']['tl_module']['cfg_instagramAppSecret'] = ['Instagram App Secret', 'Please enter the Instagram App Secret.'];
$GLOBALS['TL_LANG']['tl_module']['cfg_instagramAccessToken'] = ['Instagram access token', 'This is an auto-generated value that will be filled in when you submit the form.'];
$GLOBALS['TL_LANG']['tl_module']['cfg_instagramAccessTokenTstamp'] = ['Instagram access token last update'];
$GLOBALS['TL_LANG']['tl_module']['cfg_instagramRequestToken'] = ['Request access token and update feed', 'Check this box and save the record to request the access token and update the feed.'];
$GLOBALS['TL_LANG']['tl_module']['cfg_skipSslVerification'] = ['Skip SSL verification', 'Skip the SSL verification during API requests (not recommended).'];
$GLOBALS['TL_LANG']['tl_module']['cfg_instagramStoreFiles'] = ['Store Instagram files', 'Store the Instagram files on locally.'];
$GLOBALS['TL_LANG']['tl_module']['cfg_instagramStoreFolder'] = ['Instagram store folder', 'Please choose the Instagram store folder.'];
$GLOBALS['TL_LANG']['tl_module']['cfg_instagramMediaTypes'] = ['Instagram media types', 'Here you can choose the Instagram media types that should be shown.'];
$GLOBALS['TL_LANG']['tl_module']['cfg_instagramFetchComments'] = ['Fetch the comments data', 'Check this box to fetch the comments data.'];

/**
 * Reference types
 */
$GLOBALS['TL_LANG']['tl_module']['cfg_instagramMediaTypesRef'] = [
    'CAROUSEL_ALBUM' => 'Carousel album',
    'IMAGE' => 'Image',
    'VIDEO' => 'Video',
];
