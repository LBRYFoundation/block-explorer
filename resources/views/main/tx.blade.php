@extends('layout.default')

@section('title','Transaction '.$tx->hash)

@section('script')
    <script type="text/javascript">
        $(document).ready(function() {
            if (location.hash && (location.hash.indexOf('input-') > -1 || location.hash.indexOf('output-') > -1)) {
                $(location.hash).addClass('highlighted');
            }
        });
    </script>
@endsection

@section('content')

    @include('element.header')

    <div class="tx-head">
        <h3>LBRY Transaction</h3>
        <h4>{{ $tx->hash }}</h4>
    </div>

    <div class="tx-time">
        <div class="created-time">
            <h3 title="Represents the time this transaction was created on the explorer">Time Created</h3>
            <div>{{ $tx->created_at->format('j M Y H:i:s') . ' UTC ' }}</div>
        </div>

        <div class="conf-time">
            <h3 title="The time the first confirmation of this transaction happened on the blockchain">Block Time</h3>
            <div>
                @if($tx->transaction_time == null || strlen(trim($tx->transaction_time)) == 0)
                    <em>Not yet confirmed</em>
                @else
                    {{ \DateTime::createFromFormat('U', $tx->transaction_time)->format('j M Y H:i:s') . ' UTC' }}
                @endif
                @if($tx->transaction_time > $tx->created_at->getTimestamp())
                    @php
                        $diffSeconds = $tx->transaction_time - $tx->created_at->getTimestamp();
                        if ($diffSeconds <= 60) {
                            echo sprintf(' (+%s second%s)', $diffSeconds, $diffSeconds == 1 ? '' : 's');
                        } else {
                            $diffMinutes = ceil($diffSeconds / 60);
                            echo sprintf(' (+%s minute%s)', $diffMinutes, $diffMinutes == 1 ? '' : 's');
                        }
                    @endphp
                @endif
            </div>
        </div>

        <div class="clear"></div>
    </div>

    <div class="tx-summary">
        <div class="box p25">
            <div class="title">Amount (LBC)</div>
            <div class="value">{{ \App\Helpers\AmountHelper::format($tx->value) }}</div>
        </div>

        <div class="box p15">
            <div class="title">Block Height</div>
            @if(!isset($tx->block_hash_id) || strlen(trim($tx->block_hash_id)) === 0)
                <div class="value" title="Unconfirmed">Unconf.</div>
            @else
                <div class="value" title="{{ $tx->block_hash_id }}"><a href="/blocks/{{ $block->height }}">{{ $block->height }}</a></div>
            @endif
        </div>

        <div class="box p15">
            <div class="title">Confirmations</div>
            <div class="value">{{ $confirmations }}</div>
        </div>

        <div class="box p15">
            <div class="title">Size (bytes)</div>
            <div class="value">{{ number_format($tx->transaction_size, 0, '', ',') }}</div>
        </div>

        <div class="box p15">
            <div class="title">Inputs</div>
            <div class="value">{{ $tx->input_count }}</div>
        </div>

        <div class="box p15 last">
            <div class="title">Outputs</div>
            <div class="value">{{ $tx->output_count }}</div>
        </div>

        <div class="clear"></div>
    </div>

    <div class="tx-details">
        <h3>Details</h3>
        <div class="tx-details-layout">
            <div class="inputs">
                <div class="subtitle">{{ $tx->input_count }} input{{ $tx->input_count === 1 ? '' : 's' }}</div>
                @php($setAddressIds = [])
                @foreach($inputs as $in)
                    <div id="input-{{ $in->id }}" class="{{ 'input ' }}@if(isset($in->input_addresses) && count($in->input_addresses) > 0 && $in->input_addresses[0]->address == $sourceAddress){{ 'is-source' }}@endif">
                        @if($in->is_coinbase)
                            <div>Block Reward (New Coins)</div>
                        @else
                            @if(strlen(trim($in->value)) == 0)
                                <div>Incomplete data</div>
                            @else
                                @php($addr = $in->input_addresses[0])

                                @if(!isset($setAddressIds[$addr->address]))
                                    @php($setAddressIds[$addr->address] = 1)
                                    <a id="{{ $addr->address }}"></a>
                                @endif

                                <div><span class="value">{{ \App\Helpers\AmountHelper::format($in->value) }} LBC</span> from</div>
                                <div class="address">
                                    <a href="/address/{{ $addr->address }}">{{ $addr->address }}</a>
                                    (<a class="output-link" href="/tx/{{ $in->prevout_hash }}#output-{{ $in->prevout_n }}">output</a>)
                                    @if(isset($addr->Tag) && strlen(trim($addr->Tag)) > 0)
                                        <div class="tag">
                                            @if(strlen(trim($addr->TagUrl)) > 0)
                                                <a href="{{ $addr->TagUrl }}" target="_blank" rel="nofollow">{{ $addr->Tag }}</a>
                                            @else
                                                {{ $addr->Tag }}
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            @endif
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="divider">
                <img src="{{ '/img/right-arrow.png' }}" alt="->" />
            </div>

            <div class="outputs">
                <div class="subtitle">
                    {{ $tx->output_count }} output{{ $tx->output_count === 1 ? '' : 's' }}
                    @if($fee > 0)
                        <span class="fee"><span class="label">Fee</span> <span class="value">{{ \App\Helpers\AmountHelper::format($fee) }} LBC</span></span>
                    @endif
                </div>
                @foreach($outputs as $out)
                    <div id="output-{{ $out->vout }}" class="{{ 'output ' }}@if(isset($out->output_addresses) && count($out->output_addresses) > 0 && $out->output_addresses[0]->address == $sourceAddress){{ 'is-source' }}@endif">
                        <div class="labels">
                            @if($out->Claim && ($out->IsClaim or $out->IsSupportClaim or $out->IsUpdateClaim))<a class="view-claim" href="{{ $out->Claim->getExplorerLink() }}">View</a>@endif
                            @if($out->IsSupportClaim)<div class="support">SUPPORT</div>@endif
                            @if($out->IsUpdateClaim)<div class="update">UPDATE</div>@endif
                            @if($out->IsClaim)<div class="claim">CLAIM</div>@endif
                        </div>
                        @if(strlen(trim($out->value)) == 0)
                            <div>Incomplete data</div>
                        @else
                            @php($addr = $out->output_addresses[0])

                            @if(!isset($setAddressIds[$addr->address]))
                                @php($setAddressIds[$addr->address] = 1)
                                <a id="{{ $addr->address }}"></a>
                            @endif

                            <div><span class="value">{{ \App\Helpers\AmountHelper::format($out->value) }} LBC</span> to</div>
                            <div class="address"><a href="/address/{{ $addr->address }}">{{ $addr->address }}</a>
                                @if($out->is_spent)
                                    <a href="/tx/@if(isset($out->spend_input_id)){{ $out->spend_input_hash }}@endif#input-@if(isset($out->spend_input_id)){{ $out->spend_input_id }}@endif">spent</a>
                                @else
                                    {{ '(unspent)' }}
                                @endif

                                @if(isset($addr->Tag) && strlen(trim($addr->Tag)) > 0)
                                    <div class="tag">
                                        @if(strlen(trim($addr->TagUrl)) > 0)
                                            <a href="{{ $addr->TagUrl }}" target="_blank" rel="nofollow">{{ $addr->Tag }}</a>
                                        @else
                                            {{ $addr->Tag }}
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>

@endsection
