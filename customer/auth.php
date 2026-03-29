<?php
// auth.php

require_once 'config.php';

function getAccessToken() {
    $credentials = base64_encode(CONSUMER_KEY . ':' . CONSUMER_SECRET);

    $ch = curl_init(AUTH_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ["Authorization: Basic $credentials"],
    ]);

    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (isset($response['access_token'])) {
        return $response['access_token'];
    }

    throw new Exception('Failed to get access token: ' . json_encode($response));
}
