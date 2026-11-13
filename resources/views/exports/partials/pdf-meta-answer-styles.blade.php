/* Meta answer blanks */
.meta-prompt {
    font-size: 9pt;
    color: #333;
    text-align: center;
}

.meta-blanks {
    position: relative;
    height: {{ \App\Services\PdfMetaAnswerBlanks::SLOT_HEIGHT }}pt;
    margin: 0 0 {{ \App\Services\PdfMetaAnswerBlanks::LINE_SPACING }}pt 0;
}

.meta-blanks div {
    position: absolute;
    top: 0;
    height: {{ \App\Services\PdfMetaAnswerBlanks::SLOT_HEIGHT - 1.5 }}pt;
    font-size: 11pt;
    line-height: {{ \App\Services\PdfMetaAnswerBlanks::SLOT_HEIGHT - 1.5 }}pt;
    text-align: center;
}

.meta-blanks div.meta-slot {
    border-bottom: 1.5pt solid #000;
}
