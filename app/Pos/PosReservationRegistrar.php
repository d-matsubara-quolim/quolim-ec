<?php

namespace App\Pos;

use App\Pos\Exceptions\PosRegistrationException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\QueryException;
use InvalidArgumentException;
use PDOException;

/**
 * 予約を POS（予約台帳）に登録する。
 *
 * 旧 Web 予約の POS 取込（ImportPosReservation.php → UpdateSlip.php / GetSlipDetail.php / UpdateOrder.php）を、
 * 1 トランザクションに統合して移植したもの。登録内容・登録順序は旧処理と同じ。
 * 旧処理との違い：注文明細の単価はサイトの表示価格を使う（旧処理は P_PRODUCT_MST.PRICE）。
 */
final class PosReservationRegistrar
{
    private const string NOTES_PREFIX = '【お客様からのご要望】';

    private const int TRANSACTION_ATTEMPTS = 3;

    private const int BRAND_BRANCH_NO = 1;

    private const int CUSTOMER_BASE_ID = 1;

    private const int SLIP_STATUS_RESERVED = 1;

    private const int RESERVATION_STATUS = 1;

    private const int ORDER_STATUS_ORDERED = 1;

    private const int ORDER_CATEGORY_COURSE = 2;

    private const int DAYPART_NOON = 1;

    private const int DAYPART_NIGHT = 2;

    private const string DAYPART_NIGHT_FROM = '17:00';

    private const int DISCOUNT_UNIT_RATE = 0;

    private const int DISCOUNT_UNIT_AMOUNT = 1;

    public function __construct(
        private readonly ConnectionResolverInterface $connections,
        private readonly SlipNumberGenerator $slipNumbers,
        private readonly UpdateHistoryRecorder $history,
    ) {}

    /**
     * @throws PosRegistrationException
     */
    public function register(PosReservation $reservation): PosRegistrationResult
    {
        $brand = config("pos.brands.{$reservation->brandId}")
            ?? throw new InvalidArgumentException("Unknown POS brand: {$reservation->brandId}");

        if ($reservation->items === []) {
            throw new InvalidArgumentException('A reservation needs at least one item.');
        }

        $db = $this->connections->connection(config('pos.connection'));

        try {
            return $db->transaction(
                fn (ConnectionInterface $db) => $this->insert($db, $reservation, $brand),
                self::TRANSACTION_ATTEMPTS,
            );
        } catch (QueryException|PDOException $e) {
            throw PosRegistrationException::fromDatabaseError($e);
        }
    }

    /**
     * @param  array{room_id: int, group_product_ids: list<int>, drink_group_product_id: int, food_group_product_id: int}  $brand
     */
    private function insert(ConnectionInterface $db, PosReservation $reservation, array $brand): PosRegistrationResult
    {
        $operator = (int) config('pos.updated_by_id');

        // UpdateSlip.php
        $slipNo = $this->slipNumbers->next($db, $reservation->slipDate, $reservation->brandId, self::BRAND_BRANCH_NO);
        $slipId = $this->insertSlip($db, $reservation, $slipNo, $operator);
        $this->insertRow($db, 'P_SLIP_ROOM_TRN', [
            'SLIP_ID' => $slipId,
            'ROOM_ID' => $brand['room_id'],
        ], $operator, historySlipId: $slipId);

        $slipDetailIds = [];
        foreach ($brand['group_product_ids'] as $groupProductId) {
            $slipDetailIds[$groupProductId] = $this->insertRow($db, 'P_SLIP_DETAIL_TRN', [
                'SLIP_ID' => $slipId,
                'GROUP_PRODUCT_ID' => $groupProductId,
                // 旧処理は未指定（非 strict モードの暗黙値 0）
                'AMOUNT' => 0,
                'FIXED_PRICE' => null,
                'NOT_DISCOUNTED_PRICE' => null,
            ], $operator, historySlipId: $slipId);
        }
        $this->spreadCoursePrices($db, array_values($slipDetailIds));
        $this->updateSlipTotalPrice($db, $slipId);

        // UpdateOrder.php（ドリンク・フードの 2 注文を、明細がなくても必ず登録する）
        [$drinkItems, $foodItems] = $this->splitDrinkAndFood($reservation->items);
        $orders = [
            $slipDetailIds[$brand['drink_group_product_id']] => $drinkItems,
            $slipDetailIds[$brand['food_group_product_id']] => $foodItems,
        ];
        foreach ($orders as $slipDetailId => $items) {
            $this->insertOrder($db, $slipDetailId, $items, $operator);
        }
        foreach (array_keys($orders) as $slipDetailId) {
            $this->updateSlipDetailTotalPrice($db, $slipDetailId);
        }
        $this->updateSlipTotalPrice($db, $slipId);

        return new PosRegistrationResult($slipId, $slipNo);
    }

    private function insertSlip(ConnectionInterface $db, PosReservation $reservation, string $slipNo, int $operator): int
    {
        return $this->insertRow($db, 'P_SLIP_TRN', [
            'BRAND_ID' => $reservation->brandId,
            'SLIP_DATE' => $reservation->slipDate->toDateString(),
            'START_TIME' => $reservation->startTime,
            'END_TIME' => $reservation->endTime,
            'RESERVATION_START_LIMIT' => $reservation->startTime,
            'RESERVATION_END_LIMIT' => $reservation->endTime,
            'RESERVED_DATE' => $reservation->slipDate->toDateString().' '.$reservation->startTime,
            'CUSTOMER_NAME' => $reservation->customerName,
            'CUSTOMER_NAME_KANA' => $this->nullIfEmpty($reservation->customerNameKana),
            'TEL' => $this->nullIfEmpty($reservation->tel),
            'NOTES' => self::NOTES_PREFIX.$reservation->customerRequest,
            'IS_DELETED' => 0,
            'BRAND_BRANCH_NO' => self::BRAND_BRANCH_NO,
            'CUSTOMER_BASE_ID' => self::CUSTOMER_BASE_ID,
            'ADULT_NUMBER' => 1,
            'CHILD_NUMBER' => 0,
            'ADULT_NUMBER_LIMIT' => 0,
            'CHILD_NUMBER_LIMIT' => 0,
            'STATUS' => self::SLIP_STATUS_RESERVED,
            'RESERVATION_STATUS' => self::RESERVATION_STATUS,
            'DAYPART_KBN' => $reservation->startTime < self::DAYPART_NIGHT_FROM ? self::DAYPART_NOON : self::DAYPART_NIGHT,
            'SLIP_NO' => $slipNo,
            'DISCOUNT_NAME' => null,
            'DISCOUNT_UNIT' => null,
            'DISCOUNT_CONTENTS' => null,
            'SELF_KEY' => $this->generateSelfKey($db),
        ], $operator, historySlipId: null);
    }

    /**
     * @param  list<PosOrderItem>  $items
     */
    private function insertOrder(ConnectionInterface $db, int $slipDetailId, array $items, int $operator): void
    {
        $orderId = $this->insertRow($db, 'P_ORDER_TRN', [
            'SLIP_DETAIL_ID' => $slipDetailId,
            'ORDER_DATE' => now()->format('Y-m-d H:i:s'),
            'IS_DELETED' => 0,
        ], $operator, historySlipId: null, recordHistory: false);

        // 旧処理の履歴は SLIP_ID に注文IDが入る（明細のない注文は空文字 → 非 strict で 0）。POS 刷新まではそのまま再現する
        $this->history->recordInsert($db, $items === [] ? 0 : $orderId, 'P_ORDER_TRN', $orderId, $operator);

        foreach ($items as $item) {
            $this->insertRow($db, 'P_ORDER_DETAIL_TRN', [
                'ORDER_ID' => $orderId,
                'PRODUCT_ID' => $item->productId,
                'AMOUNT' => $item->amount,
                'TAX_RATE' => $this->taxRate($item->productId),
                'STATUS' => self::ORDER_STATUS_ORDERED,
                'IS_DELETED' => 0,
                'FIXED_PRICE' => $item->price,
                'PRICE' => $item->price,
            ], $operator, historySlipId: $orderId);
        }
    }

    /**
     * INSERT と、その更新履歴の登録。
     *
     * @param  int|null  $historySlipId  null の場合は登録した行の ID（P_SLIP_TRN 用）
     */
    private function insertRow(
        ConnectionInterface $db,
        string $table,
        array $values,
        int $operator,
        ?int $historySlipId,
        bool $recordHistory = true,
    ): int {
        $id = (int) $db->table($table)->insertGetId([
            ...$values,
            'INSERTED' => $db->raw('NOW()'),
            'UPDATED' => $db->raw('NOW()'),
            'INSERTED_BY_ID' => $operator,
            'UPDATED_BY_ID' => $operator,
        ]);

        if ($recordHistory) {
            $this->history->recordInsert($db, $historySlipId ?? $id, $table, $id, $operator);
        }

        return $id;
    }

    /**
     * 注文分類がコースの伝票明細の金額を定価・割引対象外金額にコピーする（UpdateSlip.php UpdateFinalizePerRecordSet）。
     * 旧処理は SLIP_ID 条件の UPDATE だが、ギャップロックを避けるため登録した明細の主キーで絞る。
     *
     * @param  list<int>  $slipDetailIds
     */
    private function spreadCoursePrices(ConnectionInterface $db, array $slipDetailIds): void
    {
        $placeholders = implode(',', array_fill(0, count($slipDetailIds), '?'));

        $db->update(
            "UPDATE P_SLIP_DETAIL_TRN AS SLD, P_GROUP_PRODUCT_MST AS GRP
                SET SLD.FIXED_PRICE = SLD.PRICE, SLD.NOT_DISCOUNTED_PRICE = SLD.PRICE
              WHERE SLD.GROUP_PRODUCT_ID = GRP.GROUP_PRODUCT_ID
                AND GRP.ORDER_CATEGORY_ID = ?
                AND SLD.SLIP_DETAIL_ID IN ({$placeholders})",
            [self::ORDER_CATEGORY_COURSE, ...$slipDetailIds],
        );
    }

    /**
     * 伝票明細の定価合計・割引後合計・非割引合計を更新する（Update.php UpdateSlipDetailTotalPrice）。
     */
    private function updateSlipDetailTotalPrice(ConnectionInterface $db, int $slipDetailId): void
    {
        $total = $db->selectOne(
            'SELECT SUM(ODD.AMOUNT * ODD.PRICE) AS PRICE,
                    SUM(ODD.AMOUNT * ODD.FIXED_PRICE) AS FIXED_PRICE,
                    SUM(ODD.AMOUNT * IF(ODD.DISCOUNT_UNIT IS NULL, ODD.PRICE, 0)) AS NOT_DISCOUNTED_PRICE
               FROM P_ORDER_DETAIL_TRN AS ODD
               LEFT JOIN P_ORDER_TRN AS ODR ON ODD.ORDER_ID = ODR.ORDER_ID
               LEFT JOIN P_SLIP_DETAIL_TRN AS SLD ON ODR.SLIP_DETAIL_ID = SLD.SLIP_DETAIL_ID
              WHERE SLD.SLIP_DETAIL_ID = ? AND SLD.IS_DELETED <> 1 AND ODR.IS_DELETED <> 1 AND ODD.IS_DELETED <> 1',
            [$slipDetailId],
        );

        $orderCategoryId = $db->selectOne(
            'SELECT GRP.ORDER_CATEGORY_ID AS ORDER_CATEGORY_ID
               FROM P_SLIP_DETAIL_TRN AS SLD
               LEFT JOIN P_GROUP_PRODUCT_MST AS GRP ON SLD.GROUP_PRODUCT_ID = GRP.GROUP_PRODUCT_ID
              WHERE SLD.SLIP_DETAIL_ID = ?',
            [$slipDetailId],
        )?->ORDER_CATEGORY_ID;

        if ((int) $orderCategoryId === self::ORDER_CATEGORY_COURSE) {
            return;
        }

        $db->update(
            'UPDATE P_SLIP_DETAIL_TRN SET AMOUNT = 1, PRICE = ?, FIXED_PRICE = ?, NOT_DISCOUNTED_PRICE = ? WHERE SLIP_DETAIL_ID = ?',
            [$total->PRICE, $total->FIXED_PRICE, $total->NOT_DISCOUNTED_PRICE, $slipDetailId],
        );
    }

    /**
     * 伝票の合計定価と割引後合計を更新する（Update.php UpdateSlipTotalPrice）。
     */
    private function updateSlipTotalPrice(ConnectionInterface $db, int $slipId): void
    {
        $detail = $db->selectOne(
            'SELECT SUM(AMOUNT * PRICE) AS TOTAL_PRICE,
                    SUM(AMOUNT * FIXED_PRICE) AS TOTAL_FIXED_PRICE,
                    SUM(AMOUNT * NOT_DISCOUNTED_PRICE) AS TOTAL_NOT_DISCOUNTED_PRICE
               FROM P_SLIP_DETAIL_TRN WHERE IS_DELETED <> 1 AND SLIP_ID = ?',
            [$slipId],
        );
        $slip = $db->selectOne(
            'SELECT DISCOUNT_UNIT, DISCOUNT_CONTENTS FROM P_SLIP_TRN WHERE IS_DELETED <> 1 AND SLIP_ID = ?',
            [$slipId],
        );

        $totalPrice = $detail->TOTAL_PRICE;
        if ($slip?->DISCOUNT_UNIT !== null && $totalPrice !== null) {
            $totalPrice = match ((int) $slip->DISCOUNT_UNIT) {
                self::DISCOUNT_UNIT_AMOUNT => $totalPrice - $slip->DISCOUNT_CONTENTS,
                // 端数は 1 の位を切り上げ（Update.php Settings）
                self::DISCOUNT_UNIT_RATE => ceil(($totalPrice - $detail->TOTAL_NOT_DISCOUNTED_PRICE * $slip->DISCOUNT_CONTENTS / 100) / 10) * 10,
                default => $totalPrice,
            };
        }

        $db->update(
            'UPDATE P_SLIP_TRN SET TOTAL_FIXED_PRICE = ?, TOTAL_PRICE = ? WHERE SLIP_ID = ?',
            [$detail->TOTAL_FIXED_PRICE, $totalPrice, $slipId],
        );
    }

    /**
     * @param  list<PosOrderItem>  $items
     * @return array{0: list<PosOrderItem>, 1: list<PosOrderItem>}
     */
    private function splitDrinkAndFood(array $items): array
    {
        $drinkProductIds = config('pos.drink_product_ids');
        $drinks = [];
        $foods = [];
        foreach ($items as $item) {
            if (in_array($item->productId, $drinkProductIds, true)) {
                $drinks[] = $item;
            } else {
                $foods[] = $item;
            }
        }

        return [$drinks, $foods];
    }

    private function taxRate(int $productId): int
    {
        return in_array($productId, config('pos.tax10_product_ids'), true) ? 10 : (int) config('pos.default_tax_rate');
    }

    /**
     * セルフオーダー認証キー（UpdateSlip.php getSelfKey）。
     */
    private function generateSelfKey(ConnectionInterface $db): string
    {
        do {
            $key = bin2hex(random_bytes(16));
        } while ($db->table('P_SLIP_TRN')->where('SELF_KEY', $key)->exists());

        return $key;
    }

    private function nullIfEmpty(string $value): ?string
    {
        return $value === '' ? null : $value;
    }
}
