<?php

/*
|--------------------------------------------------------------------------
| LINE WORKS（Bot）
|--------------------------------------------------------------------------
|
| erp-quolim と同じ Bot・認証情報を使う。通知先チャネルは
| config/logging.php の lineworks チャネル（LINEWORKS_ERROR_CHANNEL_ID）。
|
*/

return [

    'client_id' => env('LINEWORKS_CLIENT_ID'),

    'client_secret' => env('LINEWORKS_CLIENT_SECRET'),

    'service_account' => env('LINEWORKS_SERVICE_ACCOUNT'),

    // PEM 形式。.env に 1 行で書く場合は改行を \n にしてよい
    'private_key' => env('LINEWORKS_PRIVATE_KEY'),

    'bot_id' => env('LINEWORKS_BOT_ID'),

];
