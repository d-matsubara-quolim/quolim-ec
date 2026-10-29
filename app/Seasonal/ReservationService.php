<?php

namespace App\Seasonal;

use App\Pos\Exceptions\PosRegistrationException;
use App\Pos\PosOrderItem;
use App\Pos\PosReservation;
use App\Pos\PosReservationRegistrar;
use App\Seasonal\Exceptions\ReservationException;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 季節商品予約の確定。
 *
 * 確認画面で発行したトークン 1 つにつき、POS への登録は 1 回だけ行う。
 * 同じトークンでの再送信（二重クリック・リロード・戻る→再送信）には、最初の登録結果を返す。
 */
final class ReservationService
{
    // POS 登録はロック待ち 50 秒 × 最大 3 回のリトライがあり得るため、それより長く保持する
    private const int LOCK_SECONDS = 180;

    private const int LOCK_WAIT_SECONDS = 20;

    private const int RESULT_TTL_SECONDS = 86400;

    public function __construct(
        private readonly PosReservationRegistrar $registrar,
        private readonly Cache $cache,
    ) {}

    /**
     * 確認画面に進む予約内容をセッションに保存し、登録用のトークンを返す。
     */
    public function prepare(Session $session, Campaign $campaign, ReservationData $data): string
    {
        $token = Str::random(40);
        $session->put($this->pendingKey($campaign), ['token' => $token, 'data' => $data->toArray()]);

        return $token;
    }

    public function pending(Session $session, Campaign $campaign, string $token): ?ReservationData
    {
        $pending = $session->get($this->pendingKey($campaign));

        return is_array($pending) && hash_equals($pending['token'], $token)
            ? ReservationData::fromArray($pending['data'])
            : null;
    }

    /**
     * @throws ReservationException
     */
    public function reserve(Session $session, Campaign $campaign, string $token): CompletedReservation
    {
        $lock = $this->cache->lock("seasonal:reservation-lock:{$token}", self::LOCK_SECONDS);

        try {
            $lock->block(self::LOCK_WAIT_SECONDS);
        } catch (LockTimeoutException) {
            throw ReservationException::busy();
        }

        try {
            $completed = $this->completedResult($token) ?? $this->register($session, $campaign, $token);
        } finally {
            $lock->release();
        }

        $session->forget($this->pendingKey($campaign));
        $session->put($this->completedKey($campaign), $completed->toArray());

        return $completed;
    }

    public function completed(Session $session, Campaign $campaign): ?CompletedReservation
    {
        $completed = $session->get($this->completedKey($campaign));

        return is_array($completed) ? CompletedReservation::fromArray($completed) : null;
    }

    private function register(Session $session, Campaign $campaign, string $token): CompletedReservation
    {
        if (! $campaign->isOpen()) {
            throw ReservationException::closed();
        }

        $data = $this->pending($session, $campaign, $token) ?? throw ReservationException::expired();

        try {
            $result = $this->registrar->register($this->toPosReservation($campaign, $data));
        } catch (PosRegistrationException $e) {
            Log::error('Seasonal reservation: POS registration failed', [
                'campaign' => $campaign->slug,
                'store' => $data->storeCode,
                'error' => $e->getMessage(),
            ]);

            throw ReservationException::failed();
        }

        Log::info('Seasonal reservation: registered', [
            'campaign' => $campaign->slug,
            'slip_id' => $result->slipId,
            'slip_no' => $result->slipNo,
        ]);

        $completed = CompletedReservation::fromReservation($result->slipNo, $data);
        $this->cache->put($this->resultKey($token), $completed->toArray(), self::RESULT_TTL_SECONDS);

        return $completed;
    }

    private function completedResult(string $token): ?CompletedReservation
    {
        $completed = $this->cache->get($this->resultKey($token));

        return is_array($completed) ? CompletedReservation::fromArray($completed) : null;
    }

    private function toPosReservation(Campaign $campaign, ReservationData $data): PosReservation
    {
        $store = $campaign->store($data->storeCode);
        [$startTime, $endTime] = explode('-', $data->timeSlot);

        $items = array_map(
            fn (array $line) => new PosOrderItem(
                productId: $line['product']->posProductIdAt($store->code),
                amount: $line['quantity'],
                price: $line['product']->price,
            ),
            $campaign->orderLines($data->quantities),
        );

        return new PosReservation(
            brandId: $store->brandId,
            slipDate: CarbonImmutable::parse($data->date),
            startTime: $startTime,
            endTime: $endTime,
            customerName: $data->name,
            customerNameKana: $data->kana,
            tel: $data->tel,
            customerRequest: $data->note,
            items: $items,
        );
    }

    private function pendingKey(Campaign $campaign): string
    {
        return "seasonal.{$campaign->slug}.pending";
    }

    private function completedKey(Campaign $campaign): string
    {
        return "seasonal.{$campaign->slug}.completed";
    }

    private function resultKey(string $token): string
    {
        return "seasonal:reservation-result:{$token}";
    }
}
