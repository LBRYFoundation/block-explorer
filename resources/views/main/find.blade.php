@extends('layout.default')

@section('title','Search Results')

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

    <div class="claims-head">
        <h3>Search results</h3>
    </div>

    <div class="claims-grid">
        @if(isset($claims) && count($claims)>0)
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
                @php
                    $idx++;
                @endphp
            @endforeach
        @else
            <div class="no-results">No results were found.</div>
        @endif
        <div class="clear"></div>
    </div>

    @include('element.pagination')

@endsection
