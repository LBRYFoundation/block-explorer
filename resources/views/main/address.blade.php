@extends('layout.default')
{{ /**@var \App\Models\Address $address*/'' }}

@section('title','Address '.$address->address)

@section('script')
    <script type="text/javascript">
        var buildTagRequest = function() {
            return {
                tag: $.trim($('input[name="tag_value"]').val()),
                url: $.trim($('input[name="tag_url"]').val()),
                vamount: parseFloat($('input[name="tag_verify_amount"]').val())
            };
        };

        $(document).ready(function() {
            $('.tag-link').on('click', function(evt) {
                evt.preventDefault();
                var container = $('.tag-address-container');
                if (!container.is(':visible')) {
                    container.slideDown(200);
                }
            });

            $('.btn-tag').on('click', function(evt) {
                evt.preventDefault();
                var btn = $(this);
                var req = buildTagRequest();

                var err = $('.tag-address-container .error-message');
                err.css({ color: '#ff0000' }).text('');
                if (req.tag.length === 0 || req.tag.length > 30) {
                    return err.text('Oops! Please specify a valid tag. It should be no more than 30 characters long.');
                }

                if (req.url.length > 200) {
                    return err.text('Oops! The link should be no more than 200 characters long.');
                }

                if (isNaN(req.vamount)) {
                    return err.text('Oops! Invalid verification amount. Please refresh the page and try again.');
                }

                var btnClose = $('.btn-close');
                $.ajax({
                    url: '/api/v1/address/{{ $address->address }}/tag',
                    type: 'post',
                    dataType: 'json',
                    data: req,
                    beforeSend: function() {
                        btn.prop('disabled', true);
                        btnClose.prop('disabled', true);
                        btn.text('Loading...');
                    },
                    success: function(response) {
                        if (response.success) {
                            err.css({ color: '#00aa00'}).html('Your request for the tag, <strong>' + response.tag + '</strong> was successfully submitted. The tag will become active upon automatic transaction verification.');
                        }
                    },
                    error: function(xhr) {
                        var error = 'An error occurred with the request. If this problem persists, please send an email to hello@aureolin.co.';
                        try {
                            var json = JSON.parse(xhr.responseText);
                            if (json.error) {
                                error = json.message ? json.message : error;
                            }
                        } catch (e) {
                            // return default error
                        }
                        err.css({ color: '#ff0000' }).text(error);
                    },
                    complete: function() {
                        btn.text('Tag address');
                        btn.prop('disabled', false);
                        btnClose.prop('disabled', false);
                    }
                });
            });

            $('.btn-close').on('click', function() {
                $('input[name="tag_value"]').val('');
                $('input[name="tag_url"]').val('');
                $('.tag-address-container').slideUp(200);
            });
        });
    </script>
@endsection

@section('content')

    @include('element.header')

    <div class="address-head">
        <h3>LBRY Address</h3>
        <h4>{{ $address->address }}</h4>
        @if(isset($address->Tag) && strlen(trim($address->Tag)) > 0)
            @if(strlen(trim($address->TagUrl)) > 0)
                <a href="{{ $address->TagUrl }}" target="_blank" rel="nofollow">{{ $address->Tag }}</a>
            @else
                {{ $address->Tag }}
            @endif
        @endif
    </div>

    <div class="address-subhead">
        <div class="address-qr">
            <img src="/qr/lbry%3A{{ $address->address }}" alt="lbry:{{ $address->address }}" />
        </div>

        <div class="address-summary">
            <div class="box">
                <div class="title">Balance (LBC)</div>
                <div class="value">{{ \App\Helpers\AmountHelper::format($balanceAmount) }}</div>
            </div>

            <div class="box">
                <div class="title">Received (LBC)</div>
                <div class="value">{{ \App\Helpers\AmountHelper::format($totalReceived) }}</div>
            </div>

            <div class="box last">
                <div class="title">Sent (LBC)</div>
                <div class="value">{{ \App\Helpers\AmountHelper::format($totalSent) }}</div>
            </div>

            <div class="clear"></div>
        </div>

        <div class="clear"></div>
    </div>

    <div class="recent-transactions">
        <h3>Transactions</h3>
        <div class="results-meta">
            @if($numRecords > 0)
                @php($begin = ($currentPage - 1) * $pageLimit + 1)
                Showing {{ number_format($begin, 0, '', ',') }} - {{ number_format(min($numRecords, ($begin + $pageLimit) - 1), 0, '', ',') }} of {{ number_format($numRecords, 0, '', ',') }} transaction{{ $numRecords == 1 ? '' : 's' }}
            @endif
        </div>

        <table class="table tx-table">
            <thead>
            <tr>
                <th class="w125 left">Height</th>
                <th class="w250 left">Transaction Hash</th>
                <th class="left">Timestamp</th>
                <th class="w125 right">Confirmations</th>
                <th class="w80 right">Inputs</th>
                <th class="w80 right">Outputs</th>
                <th class="w225 right">Amount</th>
            </tr>
            </thead>

            <tbody>
            @if(count($recentTxs) == 0)
                <tr>
                    <td class="nodata" colspan="7">There are no recent transactions to display for this wallet.</td>
                </tr>
            @endif

            @foreach($recentTxs as $tx)
                <tr>
                    <td class="w125">@if($tx->height === null)<em>Unconfirmed</em>@else<a href="/blocks/{{ $tx->height }}">{{ $tx->height }}</a>@endif</td>
                    <td class="w250"><div><a href="/tx/{{ $tx->hash }}?address={{ $address->address }}#{{ $address->address }}">{{ $tx->hash }}</a></div></td>
                    <td>{{ \DateTime::createFromFormat('U', $tx->transaction_time)->format('d M Y H:i:s') . ' UTC' }}</td>
                    <td class="right">{{ number_format($tx->confirmations, 0, '', ',') }}</td>
                    <td class="right">{{ $tx->input_count }}</td>
                    <td class="right">{{ $tx->output_count }}</td>
                    <td class="right{{ ' ' . ($tx->debit_amount > 0 && $tx->credit_amount > 0 ? 'diff' : ($tx->debit_amount > 0 ? 'debit' : 'credit')) }}">
                            {{ number_format($tx->credit_amount - $tx->debit_amount, 8, '.', ',') }} LBC
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    @include('element.pagination')

@endsection
