<?php

require_once __DIR__.'/../config.php';

function userCredentials($token) {
    global $config;

    if(!$config->taskEditorApi) {
        return false;
    }

    $ch = curl_init();
    $url = $config->taskEditorApi.'/auth/credentials';
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $headers = [
        'Content-Type: application/json'
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
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
