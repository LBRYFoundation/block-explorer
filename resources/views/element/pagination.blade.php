@if(isset($numPages) && $numPages > 1)
    <div class="pagination">
        <div class="prev">
            @if($currentPage > 1)
                <a href="?page={{ $currentPage - 1 }}">Previous</a>
            @endif
            &nbsp;
        </div>
        <div class="pages">
            @if($numRecords > 0)
                @php
                    $start = $numPages > 1 ? 1 : 0;
                    $end = $numPages > 1 ? min($numPages, 10) : 0;
                    // use currentPage as the starting point
                    if ($numPages > 10) {
                        if ($currentPage > 5) {
                            $start = $currentPage < 10 ? 1 : $currentPage - 5;
                            $end = ($currentPage > ($numPages - 10) && $start > 5) ? $numPages : min($currentPage + 5, $numPages);
                        }
                    }
                @endphp
                @if($start >= 5)
                    <div class="page-number"><a href="?page=1">1</a></div>
                    <div class="page-number">...</div>
                @endif

                @if($start > 0)
                    @for($i = $start; $i <= $end; $i++)
                        <div class="page-number">
                            @if($currentPage == $i)
                                {{ $i }}
                            @else
                                <a href="?page={{ $i }}">{{ $i }}</a>
                            @endif
                        </div>
                    @endfor
                @endif

                @if($end < $numPages - 1)
                    <div class="page-number">...</div>
                    <div class="page-number">
                        <a href="?page={{ $numPages }}">{{ $numPages }}</a>
                    </div>
                @endif
            @endif
        </div>
        <div class="next">
            @if($currentPage < $numPages)
                <a href="?page={{ $currentPage + 1 }}">Next</a>
            @endif
            &nbsp
        </div>
        <div class="clear"></div>
    </div>
@endif
