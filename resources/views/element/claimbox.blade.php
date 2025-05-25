@php
    $autoThumbText = $claim->getAutoThumbText();
    $cost = '';
    if (isset($claim->price) && $claim->price > 0) {
        $cost = \App\Helpers\AmountHelper::formatCurrency($claim->price) . ' LBC';
    } else if (isset($claim->fee) && strtolower($claim->fee_currency) === 'lbc') {
        $cost = \App\Helpers\AmountHelper::formatCurrency($claim->fee) . ' LBC';
    }
    $a = ['purple', 'orange', 'blue', 'teal', 'green', 'yellow'];
    // content type
    $ctTag = $claim->getContentTag();
@endphp
<div data-id="{{ $claim->claim_id }}" class="{{ 'claim-grid-item' }}@if($idx % 3 == 0){{ ' last-item' }}@endif{{ '' }}@if($last_row){{ ' last-row' }}@endif">
    @if(strlen(trim($cost)) > 0)
        <div class="price-tag">{{ $cost }}</div>
    @endif

    <div class="tags">
        @if($claim->bid_state == 'Controlling')
            <div class="bid-state">Controlling</div>
        @endif
        @if($ctTag)
            <div class="content-type">{{ strtoupper($ctTag) }}</div>
        @endif
        @if($claim->is_nsfw)
            <div class="nsfw">NSFW</div>
        @endif
    </div>

    <div data-autothumb="{{ $autoThumbText }}" class="thumbnail {{ $a[mt_rand(0, count($a) - 1)] }}">
        @if(!$claim->is_nsfw && strlen(trim($claim->thumbnail_url)) > 0)
            <img src="{{ 'https://thumbnails.odycdn.com/optimize/s:1280:720/quality:85/plain/'.$claim->thumbnail_url }}" alt="" />
        @else
            <div class="autothumb">{{ $autoThumbText }}</div>
        @endif
    </div>

    @if($claim->isBlocked)
        <div class="blocked-info">
            In response to a complaint we received under the US Digital Millennium Copyright Act, we have blocked access to this content from our applications. For more information, please refer to <a href="https://lbry.com/faq/dmca" target="_blank">DMCA takedown requests</a>
        </div>
    @else
        <div class="metadata">
            <div class="title" title="{{ $claim->claim_type == 1 ? $claim->name : ((strlen(trim($claim->title)) > 0) ? $claim->title : '') }}">@if($claim->claim_type == 1){{ $claim->name }}@else{{ '' }}@if(strlen(trim($claim->title)) > 0){{ $claim->title }}@else<em>No Title</em>@endif{{ '' }}@endif</div>
            <div class="link" title="{{ $claim->getLbryLink() }}"><a href="{{ $claim->getLbryLink() }}" rel="nofollow">{{ $claim->getLbryLink() }}</a></div>

            <div class="desc">@if(strlen(trim($claim->description)) > 0){{ $claim->description }}@else<em>No description available</em>@endif</div>

            <div class="label half-width">Transaction</div>
            <div class="label half-width">Created</div>

            <div class="value half-width"><a href="/tx/{{ $claim->transaction_hash_id }}#output-{{ $claim->vout }}" title="{{ $claim->transaction_hash_id }}">{{ $claim->transaction_hash_id }}</a></div>
            <div class="value half-width" title="{{ $claim->created_at->format('j M Y H:i:s') }} UTC">
                {{ \Carbon\Carbon::createFromTimestamp($claim->created_at->format('U'))->diffForHumans() }}
            </div>
            <div class="clear spacer"></div>

            @if($claim->claim_type == 1)
                <div class="label half-width">Content Type</div>
                <div class="label half-width">Language</div>

                <div class="value half-width" title="{{ $claim->content_type }}">{{ $claim->content_type }}</div>
                <div class="value half-width" title="{{ $claim->language == 'en' ? 'English' : $claim->language }}">{{ $claim->language == 'en' ? 'English' : $claim->language }}</div>

                <div class="clear spacer"></div>

                {{--<div class="label half-width">Author</div>--}}
                {{--<div class="label half-width">License</div>--}}

                {{--<div class="value half-width" title="{{ strlen(trim($claim->author)) > 0 ? $claim->author : 'Unspecified' }}">@if(strlen(trim($claim->author)) > 0){{ $claim->author }}@else<em>Unspecified</em>@endif</div>--}}

                {{--<div class="value half-width" title="{{ strlen(trim($claim->license)) > 0 ? $claim->license : '' }}">--}}
                {{--    @if(strlen(trim($claim->LicenseUrl)) > 0)--}}
                {{--        <a href="{{ $claim->LicenseUrl }}" rel="nofollow" target="_blank">--}}
                {{--        @if(strlen(trim($claim->License)) > 0){{ $claim->License }}@else<em>Unspecified</em>@endif--}}
                {{--        </a>--}}
                {{--    @else--}}
                {{--        @if(strlen(trim($claim->License)) > 0){{ $claim->License }}@else<em>Unspecified</em>@endif--}}
                {{--    @endif--}}
                {{--</div>--}}
            @endif
        </div>
    @endif
</div>
