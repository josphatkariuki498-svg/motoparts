<?php
// callback.php

$callbackData = json_decode(file_get_contents('php://input'), true);

// Log everything for debugging
file_put_contents('mpesa_log.txt', json_encode($callbackData, JSON_PRETTY_PRINT) . PHP_EOL, FILE_APPEND);

$body = $callbackData['Body']['stkCallback'] ?? null;

if (!$body) {
    http_response_code(400);
    exit;
}

$resultCode = $body['ResultCode'];  // 0 = success

if ($resultCode === 0) {
    // Payment successful — extract details
    $items = [];
    foreach ($body['CallbackMetadata']['Item'] as $item) {
        $items[$item['Name']] = $item['Value'] ?? null;
    }

    $mpesaReceiptNumber = $items['MpesaReceiptNumber'];
    $amount             = $items['Amount'];
    $phone              = $items['PhoneNumber'];
    $transactionDate    = $items['TransactionDate'];

    // TODO: Update your database here
    // e.g. markOrderAsPaid($mpesaReceiptNumber, $amount, $phone);

    error_log("Payment received: KSh $amount from $phone — Ref: $mpesaReceiptNumber");
} else {
    // Payment failed or cancelled
    $reason = $body['ResultDesc'];
    error_log("Payment failed: $reason");
}

// Always respond with 200 so Safaricom stops retrying
http_response_code(200);
echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
