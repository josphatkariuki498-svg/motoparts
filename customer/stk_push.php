<?php
// stk_push.php

require_once 'config.php';
require_once 'auth.php';

function stkPush($phone, $amount, $reference = 'Order', $description = 'Payment') {
    // Sanitize phone: convert 07XX to 2547XX
    $phone = preg_replace('/^0/', '254', $phone);
    $phone = preg_replace('/^\+/', '', $phone);

    $token     = getAccessToken();
    $timestamp = date('YmdHis');
    $password  = base64_encode(SHORTCODE . PASSKEY . $timestamp);

    $payload = [
        'BusinessShortCode' => SHORTCODE,
        'Password'          => $password,
        'Timestamp'         => $timestamp,
        'TransactionType'   => 'CustomerPayBillOnline',
        'Amount'            => (int) $amount,
        'PartyA'            => $phone,
        'PartyB'            => SHORTCODE,
        'PhoneNumber'       => $phone,
        'CallBackURL'       => CALLBACK_URL,
        'AccountReference'  => $reference,
        'TransactionDesc'   => $description,
    ];

    $ch = curl_init(STK_PUSH_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer $token",
            "Content-Type: application/json",
        ],
    ]);

    $response = json_decode(curl_exec($ch), true);
    ($ch);

    return $response;
}
