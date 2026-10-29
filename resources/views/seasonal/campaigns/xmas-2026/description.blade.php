<div class="campaign-description">
    <p>{{ $campaign->description }}</p>
    <p>ご予約の受付期間：{{ $campaign->opensAt->format('Y年n月j日') }}〜{{ $campaign->closesAt->format('n月j日 H:i') }}</p>
    <p>＊ titaのオードブル以下の商品をご注文のお客様は「フラットリア チタ」をお選びください。</p>
</div>
