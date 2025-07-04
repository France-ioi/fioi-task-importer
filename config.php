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

$config->minimumFreeSpace = 0;

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
$config->codecastUrl = 'https://codecast.example.com/?taskId=';
$config->codecastLtiUrl = 'https://lti.algorea.org/?taskUrl=https%3A%2F%2Fcodecast.example.com%2F%3FtaskId%3D';
$config->notebookUrl = '';

$config->zipDir = __DIR__.'/files/zips/';

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

$config->bebrasServerModules = (object) array();
$config->bebrasServerModules->quiz_url = null;

$config->git = (object) array();
$config->git->allowedRepositories = null;
$config->git->githubUser = null;
$config->git->githubPassword = null;
$config->git->gitlabUser = null;
$config->git->gitlabPassword = null;

$config->editors = [];
$config->newEditionEndpoint = null;
$config->newEditorApiEndpoint = null;

if (is_readable(__DIR__.'/config_local.php')) {
   include_once __DIR__.'/config_local.php';
}
