<?php

/*
|--------------------------------------------------------------------------
| POS（予約台帳）連携
|--------------------------------------------------------------------------
|
| 旧 Web 予約の POS 取込バッチ（batch/AwsBatch/bin/php/ImportPosReservation.php）の
| 固定パラメータを移植したもの。キーはブランドID（= 店舗コードの数値）。
|
*/

return [

    'connection' => env('POS_CONNECTION', 'pos'),

    // クオリムくん
    'updated_by_id' => 6361,

    'default_tax_rate' => 8,

    'drink_product_ids' => [2781, 2782, 8711, 8737],

    'tax10_product_ids' => [2781, 2782, 8711, 8737],

    'brands' => [
        1 => [
            'room_id' => 169,
            'group_product_ids' => [15, 17, 18],
            'drink_group_product_id' => 15,
            'food_group_product_id' => 17,
        ],
        2 => [
            'room_id' => 163,
            'group_product_ids' => [48, 49, 50],
            'drink_group_product_id' => 48,
            'food_group_product_id' => 49,
        ],
        7 => [
            'room_id' => 161,
            'group_product_ids' => [34, 35, 36],
            'drink_group_product_id' => 34,
            'food_group_product_id' => 35,
        ],
        12 => [
            'room_id' => 165,
            'group_product_ids' => [5, 7, 8],
            'drink_group_product_id' => 5,
            'food_group_product_id' => 7,
        ],
        13 => [
            'room_id' => 155,
            'group_product_ids' => [23, 32, 33],
            'drink_group_product_id' => 23,
            'food_group_product_id' => 32,
        ],
    ],

];
