@extends('layout.default')

@section('title',isset($claim)?'Claim &bull; ' . $claim->name:'Claims Explorer')

@section('script')
    <script type="text/javascript">
        var resizeCards = function() {
            var claimInfo = $('.claim-info');
            var claimMtdt = $('.claim-metadata');
            if (claimMtdt.outerHeight() < claimInfo.outerHeight()) {
                claimMtdt.outerHeight(claimInfo.outerHeight());
            } else if (claimInfo.outerHeight() < claimMtdt.outerHeight()) {
                claimInfo.outerHeight(claimMtdt.outerHeight());
            }
        };

        window.onload = function() {
            resizeCards();
        };

        $(document).ready(function() {
            resizeCards();

            $('.claim-grid-item img,.claim-info img').on('error', function() {
                var img = $(this);
                var parent = img.parent();
                var text = parent.attr('data-autothumb');
                img.remove();
                parent.append(
                    $('<div></div>').attr({'class': 'autothumb' }).text(text)
                );
            });

            $(document).on('click', '.claim-grid-item', function() {
                var id = $(this).attr('data-id');
                location.href = '/claims/' + id;
            });
        });
    </script>
@endsection

@section('content')

    @include('element.header')

    @if(isset($claim))
        @php
            $a = ['purple', 'orange', 'blue', 'teal', 'green', 'yellow'];
            $autoThumbText = $claim->getAutoThumbText();
            $cost = 'Free';
            if (isset($claim->price) && $claim->price > 0) {
                $cost = \App\Helpers\AmountHelper::formatCurrency($claim->price) . ' LBC';
            } else if (isset($claim->fee) && strtolower($claim->fee_currency) === 'lbc') {
                $cost = \App\Helpers\AmountHelper::formatCurrency($claim->fee) . ' LBC';
            }

            $desc = $claim->description;
            if (strlen(trim($desc)) == 0) {
                $desc = '<em>No description available.</em>';
            } else {
                $desc = preg_replace('#((https?|ftp|lbry)://([A-Za-z0-9\-\/]+|\S*?\.\S*?))([\s)\[\]{},;"\':<]|\.\s|$)#i','<a href="$1" target="_blank" rel="nofollow">$1</a>$4', $desc);
                $desc = preg_replace('/(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/is', '<a href="mailto:$0" rel="nofollow">$0</a>', $desc);
            }
        @endphp

        <div class="claims-head">
            <h3><a href="/claims">LBRY Claims</a> &bull; {{ $claim->name }}</h3>
            <h4>{{ $claim->claim_id }}</h4>
        </div>

        <div class="claims-body">
            @if($claimIsBlocked)
                <div class="blocked-claim-info">
                    <div class="content">
                        In response to a complaint we received under the US Digital Millennium Copyright Act, we have blocked access to this content from our applications. For more information, please refer to <a href="https://lbry.com/faq/dmca" target="_blank">DMCA takedown requests</a>.
                    </div>
                </div>
            @else
                <div class="claim-info">
                    <div data-autothumb="{{ $autoThumbText }}" class="thumbnail {{ $a[mt_rand(0, count($a) - 1)] }}">
                        @if(!$claim->is_nsfw && strlen(trim($claim->thumbnail_url)) > 0)
                            <img src="{{ 'https://thumbnails.odycdn.com/optimize/s:1280:720/quality:85/plain/'.$claim->thumbnail_url }}" alt="" />
                        @else
                            <div class="autothumb">{{ $autoThumbText }}</div>
                        @endif
                    </div>

                    <div class="content">
                        @if($claim->claim_type == 1)
                            <div class="label">Published By</div>
                            <div class="value">
                                @if(isset($claim->publisher))
                                    <a href="lbry://{{ $claim->publisher }}">{{ $claim->publisher }}</a>
                                @else
                                    <em>Anonymous</em>
                                @endif
                            </div>
                        @endif

                        <div class="label">Created On</div>
                        <div class="value">{{ \DateTime::createFromFormat('U', $claim->transaction_time > 0 ? $claim->transaction_time : $claim->created_at->format('U'))->format('j M Y H:i:s') }} UTC</div>

                        <div class="label">Transaction ID</div>
                        <div class="value"><a href="/tx/{{ $claim->transaction_hash_id }}#output-{{ $claim->vout }}">{{ $claim->transaction_hash_id }}</a></div>

                        @if($claim->claim_type == 1)
                            <div class="label half-width">Cost</div>
                            <div class="label half-width">Safe for Work</div>

                            <div class="value half-width">{{ $cost }}</div>
                            <div class="value half-width">{{ $claim->is_nsfw ? 'No' : 'Yes' }}</div>

                            <div class="clear"></div>
                        @endif
                    </div>
                </div>

                <div class="claim-metadata">
                    @if($claim->claim_type == 2)
                        <div class="title">Identity Claim</div>
                        <div class="desc">This is an identity claim.</div>
                    @else
                        <div class="title">{{ $claim->title }}</div>
                        <div class="desc">{{ str_replace("\n", '<br />', $desc) }}</div>

                        <div class="details">
                            <div class="label half-width">Author</div>
                            <div class="label half-width">Content Type</div>

                            <div class="value half-width">@if(strlen(trim($claim->author)) > 0){{ $claim->author }}@else<em>Unspecified</em>@endif</div>
                            <div class="value half-width">@if(strlen(trim($claim->content_type)) > 0){{ $claim->content_type }}@else<em>Unspecified</em>@endif</div>

                            {{--<div class="label half-width">License</div>--}}
                            <div class="label">Language</div>

                            {{--<div class="value half-width" title="@if(strlen(trim($claim->license)) > 0){{ $claim->license }}@endif">--}}
                            {{--    @if(strlen(trim($claim->License)) > 0)--}}
                            {{--        {{ $claim->License }}--}}
                            {{--    @else--}}
                            {{--        <em>Unspecified</em>--}}
                            {{--    @endif--}}
                            {{--</div>--}}

                            <div class="value half-width">
                                @if(strlen(trim($claim->language)) > 0)
                                    {{ $claim->language == 'en' ? 'English' : '' }}
                                @else
                                    <em>Unspecified</em>
                                @endif
                            </div>
                        </div>
                    @endif
                    <a href="{{ $claim->getLbryLink() }}" class="open-lbry-link">Open in LBRY</a>
                </div>

                <div class="clear"></div>
            @endif

            @if(count($moreClaims) > 0)
                <div class="more-claims">
                    <h4>{{ isset($claim->publisher) ? 'More from the publisher' : 'Published by this identity' }}</h4>

                    <div class="claims-grid">
                        @php
                            $idx = 1;
                            $row = 1;
                            $rowCount = ceil(count($moreClaims) / 3);
                        @endphp
                        @foreach($moreClaims AS $claim)
                            @php
                                $last_row = ($row == $rowCount);
                                if ($idx % 3 == 0) {
                                    $row++;
                                }
                            @endphp
                            @include('element.claimbox',['claim'=>$claim,'idx'=>$idx,'last_row'=>$last_row])
                            @php($idx++)
                        @endforeach
                        <div class="clear"></div>
                    </div>
                </div>
            @endif
        </div>
    @else
        <div class="claims-head">
            <h2>Claims Explorer</h2>
        </div>

        <div class="claims-grid">
            @php
                $idx = 1;
                $row = 1;
                $rowCount = ceil(count($claims) / 3);
                $a = ['purple', 'orange', 'blue', 'teal', 'green', 'yellow'];
            @endphp
            @foreach($claims AS $claim)
                @php
                    $last_row = ($row == $rowCount);
                    if ($idx % 3 == 0) {
                        $row++;
                    }
                @endphp
                @include('element.claimbox',['claim'=>$claim,'idx'=>$idx,'last_row'=>$last_row])
                @php($idx++)
            @endforeach
            <div class="clear"></div>
        </div>

        @include('element.pagination')

    @endif

@endsection
