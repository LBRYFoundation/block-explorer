@extends('layout.default')

@section('title','Stats &amp; Rich List')

@section('script')
    <script src="https://www.amcharts.com/lib/3/amcharts.js"></script>
    <script src="https://www.amcharts.com/lib/3/serial.js"></script>
    <script src="https://www.amcharts.com/lib/3/plugins/export/export.min.js"></script>
    <script src="https://www.amcharts.com/lib/3/plugins/responsive/responsive.min.js" type="text/javascript"></script>
    <script type="text/javascript" src="/js/mining-inflation-chart.js"></script>
@endsection

@section('css')
    <link rel="stylesheet" href="/css/mining-inflation-chart.css" />
    <link rel="stylesheet" href="https://www.amcharts.com/lib/3/plugins/export/export.css" />
@endsection

@section('content')
    @include('element.header')

    <div class="stats-head">
        <h3>LBRY Stats</h3>
    </div>

    <div class="stats-main">

        <div class="mining-inflation-chart-container">
            <div class="load-progress inc"></div>
            <h3>Mining Inflation Chart</h3>
            <div id="mining-inflation-chart" class="chart"></div>
            <div id="chart-export" class="btn-chart-export"></div>
        </div>

        <div class="richlist">
            <h3>LBRY Rich List (Top 500)</h3>
            <table class="table">
                <thead>
                <tr>
                    <th class="w50 right">Rank</th>
                    <th class="w300 left">Address</th>
                    <th class="w150 right">Balance (LBC)</th>
                    <th class="w150 right">Balance (USD)</th>
                    <th class="w200 left med-pad-left">First Seen</th>
                    <th class="w200 center">% Top 500</th>
                </tr>
                </thead>

                <tbody>
                @php($rank = 0)
                @foreach($richList AS $item)
                    @php($rank++)
                    <tr>
                        <td class="right topvalign">{{ $rank }}</td>
                        <td class="topvalign"><a href="/address/{{ $item->address }}" target="_blank">{{ $item->address }}</a>
                            @if(in_array($item->address, $lbryAddresses))
                                <span class="lbry-address">
                                    <img src="/img/lbry.png" height="18px" width="18px" title="Address owned by LBRY Inc."/>
                                </span>
                            @endif

                            @if(isset($item->Tag) && strlen(trim($item->Tag)) > 0)
                                <div class="tag">
                                    @if(strlen(trim($item->TagUrl)) > 0)
                                        <a href="{{ $item->TagUrl }}" target="_blank" rel="nofollow">{{ $tiem->Tag }}</a>
                                    @else
                                        {{ $item->Tag }}
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td class="right topvalign">{{ number_format($item->balance, 8, '.', ',') }}</td>
                        <td class="right topvalign">${{ number_format(bcmul($item->balance, $rate, 8), 2, '.', ',') }}</td>
                        <td class="med-pad-left topvalign">{{ $item->first_seen->format('d M Y H:i:s') . ' UTC' }}</td>
                        <td class="w150 center top500-percent-cell"><div class="top500-percent" style="width: {{ $item->MinMaxPercent }}%"></div><div class="text">{{ number_format($item->Top500Percent, 5, '.', '') }}%</div></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="clear"></div>
    </div>
@endsection
