<?php

// Do not modify this file, but override the configuration
// in a config_local.php file based on config_local_template.php

global $config;
$config = (object) array();

$config->baseUrl = '';

$config->db = (object) array();
$config->db->host = 'localhost';
$config->db->database = 'castor';
$config->db->password = 'castor';
$config->db->user = 'castor';
$config->db->logged = false;

$config->aws = (object) array();
$config->aws->key = '';
$config->aws->secret = '';
$config->aws->tmpBucket = (object) array();
$config->aws->tmpBucket->region = '';
$config->aws->tmpBucket->name = '';
$config->aws->finalBucket = (object) array();
$config->aws->finalBucket->region = '';
$config->aws->finalBucket->name = '';

if (is_readable(__DIR__.'/config_local.php')) {
   include_once __DIR__.'/config_local.php';
}