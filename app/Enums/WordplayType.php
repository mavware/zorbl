<?php

namespace App\Enums;

enum WordplayType: string
{
    case CharadePair = 'charade_pair';
    case Semordnilap = 'semordnilap';
    case BeheadmentChain = 'beheadment_chain';

    public function label(): string
    {
        return match ($this) {
            self::CharadePair => 'Charade pair',
            self::Semordnilap => 'Semordnilap',
            self::BeheadmentChain => 'Beheadment chain',
        };
    }

    public function describeNotes(array $notes): string
    {
        return match ($this) {
            self::CharadePair => collect($notes['splits'] ?? [])
                ->map(fn (array $split): string => implode('+', $split))
                ->implode(' | '),
            self::Semordnilap => (string) ($notes['reverse'] ?? ''),
            self::BeheadmentChain => implode(' -> ', $notes['chain'] ?? []),
        };
    }
}
