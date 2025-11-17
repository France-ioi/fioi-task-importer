<?php

// Functions for URL handling

require_once __DIR__.'/../config.php';
require_once __DIR__.'/../shared/TokenGenerator.php';

function unparse_url($parsed_url) {
    $scheme   = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
    $host     = isset($parsed_url['host']) ? $parsed_url['host'] : '';
    $port     = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
    $user     = isset($parsed_url['user']) ? $parsed_url['user'] : '';
    $pass     = isset($parsed_url['pass']) ? ':' . $parsed_url['pass']  : '';
    $pass     = ($user || $pass) ? "$pass@" : '';
    $path     = isset($parsed_url['path']) ? $parsed_url['path'] : '';
    $query    = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
    $fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';
    return "$scheme$user$pass$host$port$path$query$fragment";
}

function addToken($url, $token=null) {
    global $config;

    if ($token == null) {
        $token = generateToken($url);
    }
    $parsed_url = parse_url($url);
    $queryArgs = array();
    if (isset($parsed_url['query'])) {
        parse_str($parsed_url['query'], $queryArgs);
    }

    $queryArgs['sToken'] = $token;
    $queryArgs['sPlatform'] = $config->platform->name;

    $parsed_url['query'] = http_build_query($queryArgs);

    return unparse_url($parsed_url);
}

function generateToken($url) {
    global $config;

    $tokenGenerator = new TokenGenerator($config->platform->private_key, $config->platform->name);
    $params = array(
      'bAccessSolutions' => true,
      'bSubmissionPossible' => true,
      'bHintsAllowed' => true,
      'nbHintsGiven' => 10000,
      'bIsAdmin' => false,
      'bReadAnswers' => true,
      'idUser' => 0,
      'idItemLocal' => 0,
      'itemUrl' => $url,
      'randomSeed' => 0,
      'bHasSolvedTask' => false,
      'bTestMode' => true,
    );
    $sToken = $tokenGenerator->encodeJWS($params);
    return $sToken;
}

