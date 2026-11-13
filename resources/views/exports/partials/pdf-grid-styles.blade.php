{{-- CSS for the puzzle grid table. Expects $cellSize (inches), $numberFontSize, $letterFontSize and $numberHeight. --}}
/* Grid table */
.grid-table {
    border-collapse: collapse;
    margin: 0 auto;
}

.grid-table td {
    width: {{ $cellSize }}in;
    height: {{ $cellSize }}in;
    padding: 0;
    text-align: center;
    vertical-align: middle;
    border: 1pt solid #000;
    position: relative;
}

.grid-table td.block {
    background-color: #000;
    border-color: #000;
}

.grid-table td.void {
    background: none;
    border: none;
}

.circle-indicator {
    width: {{ round($cellSize * 0.82, 3) }}in;
    height: {{ round($cellSize * 0.82, 3) }}in;
    border: 0.7pt solid #555;
    border-radius: 50%;
    margin: 0 auto;
}

.grid-table td.bar-top { border-top: 2.5pt solid #000; }
.grid-table td.bar-right { border-right: 2.5pt solid #000; }
.grid-table td.bar-bottom { border-bottom: 2.5pt solid #000; }
.grid-table td.bar-left { border-left: 2.5pt solid #000; }

.cell-number {
    font-size: {{ $numberFontSize }}pt;
    font-weight: bold;
    text-align: left;
    line-height: 1;
    padding-left: 1pt;
    padding-top: 0;
    margin: 0;
    height: {{ $numberHeight }}in;
}

.cell-letter {
    font-size: {{ $letterFontSize }}pt;
    font-weight: normal;
    text-align: center;
    line-height: 1;
    margin: 0;
    padding: 0;
}

.cell-letter.prefilled {
    color: #555;
}
