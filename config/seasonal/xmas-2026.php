<?php

/*
|--------------------------------------------------------------------------
| 季節商品予約：クリスマス 2026
|--------------------------------------------------------------------------
|
| 価格・上限・受取可能店舗はサーバー側の検証と POS 登録にも使うため、ここが唯一の定義。
| 説明文の並び・装飾（HTML）は resources/views/seasonal/campaigns/xmas-2026/description.blade.php で変える。
|
| 受付期間を変更した場合は、反映のため `php artisan config:cache` を再実行すること。
|
*/

return [

    'title' => 'クリスマスケーキ・オードブル',

    // 予約フォーム上部の説明文。検索結果に表示される説明（meta description）にも使う
    'description' => 'ささいずみのパティシエがお届けするクリスマスケーキと、フラットリア チタのオードブルのWeb予約です。',

    // 説明文の下の注意書き（改行可・空なら非表示）
    'notice' => 'titaのオードブル・フライドチキン・キッシュは「フラットリア チタ」でのお受け取りとなります。',

    'cover_image' => 'https://cdn.quolim.co.jp/img/sps/top/xmas-cake.png',

    'opens_at' => env('SEASONAL_XMAS_2026_OPENS_AT', '2026-11-21 00:00:00'),

    'closes_at' => env('SEASONAL_XMAS_2026_CLOSES_AT', '2026-12-21 23:59:59'),

    'max_quantity_per_product' => 10,

    // brand_id は POS のブランドID（config/pos.php の brands のキー）
    'stores' => [
        [
            'code' => '07',
            'name' => 'サイエンス パティスリー ササ',
            'brand_id' => 7,
            'dates' => ['2026-12-24', '2026-12-25'],
            'time_slots' => [
                '12:00-13:00', '13:00-14:00', '14:00-15:00', '15:00-16:00',
                '16:00-17:00', '17:00-18:00', '18:00-19:00',
            ],
        ],
        [
            'code' => '12',
            'name' => 'フラットリア チタ',
            'brand_id' => 12,
            'dates' => ['2026-12-24', '2026-12-25'],
            'time_slots' => [
                '12:00-13:00', '13:00-14:00', '14:00-15:00', '15:00-16:00',
                '16:00-17:00', '17:00-18:00',
            ],
        ],
    ],

    // 表示順。description は商品詳細ページの説明文（改行可・空なら非表示）。
    // pos_product_ids は受取店舗ごとに POS へ登録する商品ID（2026年のみ店舗別に変換する）
    'products' => [
        [
            'id' => 7274,
            'name' => 'シャンティ・ショコラ・フレーズ',
            'name_en' => 'Chantilly Chocolat Fraise',
            'price' => 4900,
            'unit' => '台',
            'note' => '限定 50',
            'image' => 'https://cdn.quolim.co.jp/img/sps/menu/xmas-cake_002.png',
            'description' => '',
            'pos_product_ids' => ['07' => 7274, '12' => 7298],
        ],
        [
            'id' => 5298,
            'name' => 'アルブル・ド・ノエル',
            'name_en' => 'Arbre de Noël',
            'price' => 4000,
            'unit' => '台',
            'note' => '限定 20',
            'image' => 'https://cdn.quolim.co.jp/img/sps/menu/xmas-cake_001.png',
            'description' => '',
            'pos_product_ids' => ['07' => 5298, '12' => 3899],
        ],
        [
            'id' => 5300,
            'name' => 'シャンティ・フレーズ',
            'name_en' => 'Chantilly Fraise',
            'price' => 4400,
            'unit' => '台',
            'note' => '限定 50',
            'image' => 'https://cdn.quolim.co.jp/img/sps/menu/xmas-cake_003.png',
            'description' => '',
            'pos_product_ids' => ['07' => 5300, '12' => 3901],
        ],
        [
            'id' => 8866,
            'name' => 'titaのオードブル',
            'name_en' => "Hors d'oeuvre de tita",
            'price' => 3620,
            'unit' => 'セット',
            'note' => '',
            'image' => 'https://cdn.quolim.co.jp/img/tita/menu/set_004.png',
            'description' => '',
            'pos_product_ids' => ['12' => 8866],
        ],
        [
            'id' => 3833,
            'name' => 'フライドチキン（４個）',
            'name_en' => 'Poulet Frit',
            'price' => 650,
            'unit' => 'セット',
            'note' => '',
            'image' => 'https://cdn.quolim.co.jp/img/tita/menu/set_007.png',
            'description' => '',
            'pos_product_ids' => ['12' => 3833],
        ],
        [
            'id' => 8767,
            'name' => '気まぐれキッシュ（ホール）',
            'name_en' => 'Quiche (whole)',
            'price' => 2290,
            'unit' => 'セット',
            'note' => '',
            'image' => 'https://cdn.quolim.co.jp/img/tita/menu/set_008.png',
            'description' => '',
            'pos_product_ids' => ['12' => 8767],
        ],
        [
            'id' => 8768,
            'name' => '気まぐれキッシュ（1/4カット）',
            'name_en' => 'Quiche (1/4 slice)',
            'price' => 650,
            'unit' => 'セット',
            'note' => '',
            'image' => 'https://cdn.quolim.co.jp/img/tita/menu/set_009.png',
            'description' => '',
            'pos_product_ids' => ['12' => 8768],
        ],
    ],

];
