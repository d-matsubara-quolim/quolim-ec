<div class="campaign-description">
    <p>{{ $campaign->description }}</p>
    @if ($campaign->notice !== '')
        <p class="campaign-description__note">{!! nl2br(e($campaign->notice)) !!}</p>
    @endif
</div>
