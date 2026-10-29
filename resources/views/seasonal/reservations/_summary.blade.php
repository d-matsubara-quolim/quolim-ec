@use('App\Seasonal\PickupStore')

<section class="panel">
    <h2 class="panel__title">ご予約の商品</h2>
    <ul class="order-lines">
        @foreach ($lines as $line)
            <li class="order-line">
                <img src="{{ $line['product']->image }}" alt="" class="order-line__image" loading="lazy">
                <span class="order-line__body">
                    <span class="order-line__name">{{ $line['product']->name }}</span>
                    <span class="order-line__meta"><x-yen :amount="$line['product']->price" /> × {{ $line['quantity'] }}{{ $line['product']->unit }}</span>
                </span>
                <x-yen :amount="$line['subtotal']" class="order-line__subtotal" />
            </li>
        @endforeach
    </ul>
    <p class="order-total"><span>合計<span class="tax">税込</span></span><x-yen :amount="$total" class="order-total__amount" /></p>
</section>

<section class="panel">
    <h2 class="panel__title">お受け取り</h2>
    <dl class="summary-list">
        <dt>受取店舗</dt><dd>{{ $store?->name }}</dd>
        <dt>受取日</dt><dd>{{ PickupStore::formatDate($date) }}</dd>
        <dt>受取時間</dt><dd>{{ PickupStore::formatTimeSlot($timeSlot) }}</dd>
    </dl>
</section>
