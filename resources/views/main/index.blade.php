@extends('layout.default')

@section('title','Home')

@section('script')
    <script type="text/javascript">
        var updateInterval = 120000;

        var updateStatus = function() {
            $.ajax({
                url: '/api/v1/status',
                type: 'get',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        var status = response.status;
                        var stats = $('.stats');
                        stats.find('.box:eq(0) > .value').text(status.height);
                        stats.find('.box:eq(1) > .value').text(status.difficulty);
                        stats.find('.box:eq(2) > .value').text(status.hashrate);
                        stats.find('.box:eq(3) > .value').text(status.price);
                    }
                }
            });
        };

        var updateRecentBlocks = function() {
            var tbody = $('.recent-blocks .table tbody');

            $.ajax({
                url: '/api/v1/recentblocks',
                type: 'get',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        var blocks = response.blocks;
                        for (var i = blocks.length - 1; i >= 0; i--) {
                            var block = blocks[i];
                            var prevRow = tbody.find('tr[data-height="' + block.Height + '"]');
                            if (prevRow.length > 0) {
                                continue;
                            }

                            var blockTime = moment(block.BlockTime * 1000);
                            tbody.prepend(
                                $('<tr></tr>').attr({'data-height': block.Height, 'data-time': block.BlockTime}).append(
                                    $('<td></td>').append(
                                        $('<a></a>').attr({'href': '/blocks/' + block.Height}).text(block.Height)
                                    )
                                ).append(
                                    $('<td></td>').text(blockTime.fromNow())
                                ).append(
                                    $('<td></td>').attr({'class': 'right'}).text((block.BlockSize / 1024).toFixed(2) + 'KB')
                                ).append(
                                    $('<td></td>').attr({'class': 'right'}).text(block.TransactionCount)
                                ).append(
                                    $('<td></td>').attr({'class': 'right'}).text(block.Difficulty)
                                ).append(
                                    $('<td></td>').attr({'class': 'last-cell'}).text(blockTime.utc().format('D MMM YYYY HH:mm:ss') + ' UTC')
                                )
                            );

                            // Remove the last row
                            tbody.find('tr:last').remove();
                        }
                    }
                },
                complete: function() {
                    tbody.find('tr').each(function() {
                        var row = $(this);
                        var blockTime = moment(row.attr('data-time') * 1000);
                        row.find('td:eq(1)').text(blockTime.fromNow());
                    });
                }
            });
        };

        $(document).ready(function() {
            setInterval(updateStatus, updateInterval);
            setInterval(updateRecentBlocks, updateInterval);

            $(document).on('click', '.recent-claims .claim-box .tx-link', function(evt) {
                evt.stopImmediatePropagation();
            });

            $(document).on('click', '.recent-claims .claim-box', function() {
                var id = $(this).attr('data-id');
                window.location.href = '/claims/' + id;

                // center the popup
                /*var dualScreenLeft = window.screenLeft != undefined ? window.screenLeft : screen.left;
                var dualScreenTop = window.screenTop != undefined ? window.screenTop : screen.top;
                var width = window.innerWidth ? window.innerWidth : document.documentElement.clientWidth ? document.documentElement.clientWidth : screen.width;
                var height = window.innerHeight ? window.innerHeight : document.documentElement.clientHeight ? document.documentElement.clientHeight : screen.height;

                var left = ((width / 2) - (1366 / 2)) + dualScreenLeft;
                var top = ((height / 2) - (768 / 2)) + dualScreenTop;

                window.open('/claims/' + id, 'claim_details', 'width=1366,height=768,left=' + left + ',top=' + top);*/
            });

            $('.claim-box img').on('error', function() {
                var img = $(this);
                var parent = img.parent();
                var text = parent.attr('data-autothumb');
                img.remove();
                parent.append(
                    $('<div></div>').attr({'class': 'autothumb' }).text(text)
                );
            });
        });
    </script>
@endsection

@section('content')
    <div class="home-container">
        <div class="home-container-cell">
            <div class="main">
                <div class="title">LBRY Block Explorer</div><br>
                <form method="get" action="/find">
                    <input class="search-input" name="q" type="text" placeholder="Enter a block height or hash, claim id or name, transaction hash or address" />
                    <div class="ctls">
                        {{--<div class="left-links"><a href="https://lbry.com/get">Download the LBRY App</a></div>--}}
                        <button class="btn btn-search">Search</button>
                        <div class="right-links">
                            <a href="/realtime">Realtime</a> &bull; <a href="/stats" class="last">Stats</a>
                        </div>
                    </div>
                </form>
            </div>

            <div class="stats">
                <div class="box box-20">
                    <div class="title">Block Height</div>
                    <div class="value">{{ $recentBlocks[0]->height }}</div>
                </div>

                <div class="box box-30">
                    <div class="title">Difficulty</div>
                    <div class="value" title="{{ $recentBlocks[0]->difficulty }}">{{ number_format($recentBlocks[0]->difficulty, 2, '.', '') }}</div>
                </div>

                <div class="box box-30">
                    <div class="title">Network</div>
                    <div class="value">{{ $hashRate }}</div>
                </div>

                <div class="box box-20 last">
                    <div class="title">Price</div>
                    <div class="value">{{ $lbcUsdPrice }}</div>
                </div>

                <div class="clear"></div>
            </div>

            <div class="recent-blocks">
                <h3>Recent Blocks</h3>
                <a class="all-blocks-link" href="/blocks">LBRY Blocks</a>
                <table class="table table-striped">
                    <thead>
                    <tr>
                        <th class="left w125">Height</th>
                        <th class="left w125">Age</th>
                        <th class="right w150">Block Size</th>
                        <th class="right w150">Transactions</th>
                        <th class="right w150">Difficulty</th>
                        <th class="left w250 last-cell">Timestamp</th>
                    </tr>
                    </thead>

                    <tbody>
                    @foreach($recentBlocks as $block)
                        <tr data-height="{{ $block->height }}" data-time="{{ $block->block_time }}">
                            <td><a href="/blocks/{{ $block->height }}">{{ $block->height }}</a></td>
                            <td>{{ \Carbon\Carbon::createFromTimestamp($block->block_time)->diffForHumans() }}</td>
                            <td class="right">{{ round($block->block_size / 1024, 2) . 'KB' }}</td>
                            <td class="right">{{ $block->tx_count }}</td>
                            <td class="right">{{ number_format($block->difficulty, 2, '.', '') }}</td>
                            <td class="last-cell">{{ DateTime::createFromFormat('U', $block->block_time)->format('d M Y H:i:s') . ' UTC' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="recent-claims">
                <h3>Recent Claims</h3>
                <a class="claim-explorer-link" href="/claims">Claims Explorer</a>
                @php($idx = 0)
                @php($a = ['purple', 'orange', 'blue', 'teal', 'green', 'yellow'])
                @foreach($recentClaims as $claim)
                    @php($idx++)
                    @php($autoThumbText = $claim->getAutoThumbText())
                    {{--content type--}}
                    @php($ctTag = $claim->getContentTag())
                    <div data-id="{{ $claim->claim_id }}" class="{{ 'claim-box' }}@if($idx == 5){{ ' last' }}@endif">
                        <div class="tags">
                            @if($ctTag)
                                <div class="content-type">{{ strtoupper($ctTag) }}</div>
                            @endif
                            @if($claim->is_nsfw)
                                <div class="nsfw">NSFW</div>
                            @endif
                        </div>

                        <div data-autothumb="{{ $autoThumbText }}" class="thumbnail {{ $a[mt_rand(0, count($a) - 1)] }}">
                            @if(!$claim->is_nsfw && strlen(trim($claim->thumbnail_url)) > 0)
                                <img src="{{ strip_tags('https://thumbnails.odycdn.com/optimize/s:1280:720/quality:85/plain/'.$claim->thumbnail_url) }}" alt="" />
                            @else
                                <div class="autothumb">{{ $autoThumbText }}</div>
                            @endif
                        </div>

                        <div class="metadata">
                            <div class="title" title="{{ $claim->claim_type == 1 ? $claim->name : ((strlen(trim($claim->title)) > 0) ? $claim->title : '') }}">
                                @if($claim->claim_type == 1){{ $claim->name }}@else{{ '' }}@if(strlen(trim($claim->title)) > 0){{ $claim->title }}@else<em>No Title</em>@endif{{ '' }}@endif
                            </div>
                            <div class="link" title="{{ $claim->getLbryLink() }}"><a href="{{ $claim->getLbryLink() }}">{{ $claim->getLbryLink() }}</a></div>

                            <div class="clear"></div>
                            @if($claim->claim_type == 2 && strlen(trim($claim->description)) > 0)
                                <div class="desc">{{ $claim->description }}</div>
                            @endif
                        </div>

                        <a class="tx-link" href="/tx/{{ $claim->transaction_hash_id }}#output-{{ $claim->vout }}" target="_blank">Transaction</a>
                    </div>
                @endforeach

                <div class="clear"></div>
            </div>
        </div>

    </div>
@endsection
