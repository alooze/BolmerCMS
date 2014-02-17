<?php
define('CMS_NAME', 'BolmerCMS');

define('CMS_RELEASE_VERSION', '0.1.0');
define('CMS_RELEASE_NAME', 'alpha');
define('CMS_RELEASE_DATE', '17 Feb 2014');

define('CMS_FULL_APPNAME', CMS_NAME.' '.CMS_RELEASE_VERSION.' '.CMS_RELEASE_NAME.' ('.CMS_RELEASE_DATE.')');

// For backwards compatability
// ---------------------------

$modx_version = CMS_RELEASE_VERSION;
$modx_release_date = CMS_RELEASE_DATE;
$modx_branch = CMS_NAME;

$modx_full_appname = CMS_FULL_APPNAME;