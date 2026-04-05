<?php

namespace Zorbl\CrosswordIO\Exceptions;

use Exception;

class ExportValidationException extends Exception
{
    /**
     * @param  list<UnsupportedFeature>  $unsupportedFeatures
     */
    public function __construct(
        public readonly string $format,
        public readonly array $unsupportedFeatures,
    ) {
        $labels = array_map(fn (UnsupportedFeature $f) => $f->label(), $unsupportedFeatures);

        parent::__construct(
            "This crossword uses features not fully supported by {$format} export: ".implode('; ', $labels)
        );
    }
}
