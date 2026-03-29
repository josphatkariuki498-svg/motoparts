<?php
// config.php

define('CONSUMER_KEY',    'your_consumer_key_here');
define('CONSUMER_SECRET', 'your_consumer_secret_here');
define('SHORTCODE',       '174379');           // sandbox shortcode
define('PASSKEY',         'your_passkey_here');
define('CALLBACK_URL',    'https://yourdomain.com/mpesa/callback.php');

// Sandbox URLs (swap for live later)
define('AUTH_URL',     'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials');
define('STK_PUSH_URL', 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest');
