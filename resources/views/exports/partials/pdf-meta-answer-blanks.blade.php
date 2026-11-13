{{-- The meta answer's blanks beneath the grid: one underscored cell per letter, as on the solve page.
     Expects $lines (see App\Services\PdfMetaAnswerBlanks) and $width, the width they are centred in.
     Every cell is placed at its planned offset, so each line is drawn exactly as wide as it was planned. --}}
@foreach ($lines as $line)
    <div class="meta-blanks">
        @php($offset = max(0, ($width - $line['width']) / 2))
        @foreach ($line['cells'] as $cell)
            @unless ($cell['type'] === 'gap')
                <div class="meta-{{ $cell['type'] }}" style="left: {{ round($offset, 2) }}pt; width: {{ $cell['width'] }}pt;">@if ($cell['type'] === 'literal'){{ $cell['char'] }}@else&nbsp;@endif</div>
            @endunless
            @php($offset += $cell['width'])
        @endforeach
    </div>
@endforeach
