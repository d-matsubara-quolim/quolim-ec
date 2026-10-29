@use('App\Seasonal\PickupStore')

<section class="step">
    <h2 class="step__title">ご予約の商品</h2>
    <table class="order-table">
        <thead>
            <tr><th>商品</th><th class="num">単価</th><th class="num">個数</th><th class="num">小計</th></tr>
        </thead>
        <tbody>
            @foreach ($lines as $line)
                <tr>
                    <td>{{ $line['product']->name }}</td>
                    <td class="num"><x-yen :amount="$line['product']->price" /></td>
                    <td class="num">{{ $line['quantity'] }}{{ $line['product']->unit }}</td>
                    <td class="num"><x-yen :amount="$line['subtotal']" /></td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr><th colspan="3">合計（税込）</th><td class="num"><x-yen :amount="$total" /></td></tr>
        </tfoot>
    </table>
</section>

<section class="step">
    <h2 class="step__title">お受け取り</h2>
    <dl class="summary-list">
        <dt>受取店舗</dt><dd>{{ $store?->name }}</dd>
        <dt>受取日</dt><dd>{{ PickupStore::formatDate($date) }}</dd>
        <dt>受取時間</dt><dd>{{ PickupStore::formatTimeSlot($timeSlot) }}</dd>
    </dl>
</section>
