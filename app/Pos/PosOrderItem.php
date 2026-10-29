<?php

namespace App\Pos;

final readonly class PosOrderItem
{
    /**
     * @param  int  $productId  POS の商品ID（P_PRODUCT_MST.PRODUCT_ID）
     * @param  int  $price  税込単価。注文明細の定価・金額としてそのまま登録する
     */
    public function __construct(
        public int $productId,
        public int $amount,
        public int $price,
    ) {}
}
