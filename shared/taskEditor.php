<?php

require_once __DIR__.'/../config.php';

function userCredentials($token) {
    global $config;

    if(!$config->taskEditorApiUrl) {
        return false;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $config->taskEditorApiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $params = [
        'token' => $token
    ];
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
    $res = curl_exec($ch);
    curl_close($ch);

    $credentials = json_decode($res, true);
    if($credentials === null && json_last_error() !== JSON_ERROR_NONE) {
        return false;
    }
    return $credentials;
}