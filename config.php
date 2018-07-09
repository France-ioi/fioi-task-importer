<?php

// Do not modify this file, but override the configuration
// in a config_local.php file based on config_local_template.php

global $config;
$config = (object) array();

$config->baseUrl = '';
$config->svnBaseUrl = '';
$config->svnExampleUrl = '';
$config->localCssUrl = null;
$config->urlArgs = '';

$config->db = (object) array();
$config->db->host = 'localhost';
$config->db->database = 'castor';
$config->db->password = 'castor';
$config->db->user = 'castor';
$config->db->logged = false;

$config->platform = (object) array();
$config->platform->name = "";
$config->platform->private_key = "";

$config->defaultSvnUser = null;
$config->defaultSvnPassword = null;

$config->staticUrl = 'https://static.example.com/files/checkouts/';
$config->normalUrl = 'https://tasks.algorea.org/?taskId=';
$config->ltiUrl = 'https://lti.algorea.org/?taskUrl=https%3A%2F%2Ftasks.algorea.org%2Ftask.html%3FtaskId%3D';

$config->aws = (object) array();
$config->aws->key = '';
$config->aws->secret = '';
$config->aws->tmpBucket = (object) array();
$config->aws->tmpBucket->region = '';
$config->aws->tmpBucket->name = '';
$config->aws->finalBucket = (object) array();
$config->aws->finalBucket->region = '';
$config->aws->finalBucket->name = '';

//$config->taskEditorApi = 'http://localhost:8080/api';

if (is_readable(__DIR__.'/config_local.php')) {
   include_once __DIR__.'/config_local.php';
}
