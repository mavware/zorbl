<?php

namespace App\Services;

/**
 * Flags clues that break common crossword cluing conventions.
 *
 * Every issue is advisory: constructors sometimes break a rule on purpose
 * (a themed revealer that cross-references its theme entries, say), so the
 * checker reports problems but never rejects a clue.
 *
 * @phpstan-type ClueIssue array{code: string, severity: string, message: string}
 */
class ClueQualityChecker
{
    public const string SEVERITY_ERROR = 'error';

    public const string SEVERITY_WARNING = 'warning';

    public const int MIN_LENGTH = 3;

    public const int MAX_LENGTH = 120;

    /**
     * Suffixes stripped when comparing word roots, longest first so "ings"
     * wins over "s".
     *
     * @var list<string>
     */
    private const array SUFFIXES = [
        'ations', 'ation', 'ments', 'ment', 'nesses', 'ness', 'ings', 'ing',
        'ers', 'er', 'ies', 'ied', 'ist', 'ists', 'est', 'ful', 'less', 'ly',
        'es', 'ed', 's', 'y',
    ];

    /**
     * Common short words that happen to live inside longer answers
     * (WITHOUT, THEREFORE) and aren't worth flagging.
     *
     * @var list<string>
     */
    private const array STOPWORDS = [
        'about', 'after', 'also', 'been', 'from', 'have', 'here', 'into', 'like',
        'more', 'once', 'only', 'over', 'some', 'such', 'than', 'that', 'them',
        'then', 'there', 'they', 'this', 'what', 'when', 'where', 'with', 'your',
    ];

    /**
     * Check a single clue against its answer.
     *
     * @return list<ClueIssue>
     */
    public function check(string $clue, ?string $answer = null): array
    {
        $clue = trim($clue);

        if ($clue === '') {
            return [];
        }

        $issues = [];

        if ($this->isPlaceholder($clue)) {
            $issues[] = $this->warning('placeholder', 'Looks like placeholder text');
        } elseif (mb_strlen($clue) < self::MIN_LENGTH) {
            $issues[] = $this->warning('too_short', 'Clue may be too short');
        }

        if (mb_strlen($clue) > self::MAX_LENGTH) {
            $issues[] = $this->warning('too_long', 'Clue is unusually long');
        }

        array_push($issues, ...$this->answerIssues($clue, $answer));

        if ($reference = $this->crossReference($clue)) {
            $issues[] = $this->warning('cross_reference', "Depends on another clue ({$reference})");
        }

        if ($this->hasUnbalancedPunctuation($clue)) {
            $issues[] = $this->warning('unbalanced_punctuation', 'Unbalanced quotes or brackets');
        }

        return $issues;
    }

    /**
     * Check every clue in a puzzle, adding puzzle-level issues such as
     * duplicate clue text. Results are keyed "{direction}-{number}".
     *
     * @param  list<array{direction: string, number: int|string, clue?: string|null, answer?: string|null}>  $clues
     * @return array<string, list<ClueIssue>>
     */
    public function checkPuzzle(array $clues): array
    {
        $textCounts = [];

        foreach ($clues as $clue) {
            $normalized = mb_strtolower(trim((string) ($clue['clue'] ?? '')));

            if ($normalized !== '') {
                $textCounts[$normalized] = ($textCounts[$normalized] ?? 0) + 1;
            }
        }

        $results = [];

        foreach ($clues as $clue) {
            $text = (string) ($clue['clue'] ?? '');
            $issues = $this->check($text, $clue['answer'] ?? null);

            if (($textCounts[mb_strtolower(trim($text))] ?? 0) > 1) {
                $issues[] = $this->warning('duplicate', 'Duplicate clue text');
            }

            if ($issues !== []) {
                $results[$clue['direction'].'-'.$clue['number']] = $issues;
            }
        }

        return $results;
    }

    /**
     * @return list<ClueIssue>
     */
    private function answerIssues(string $clue, ?string $answer): array
    {
        $answer = mb_strtolower((string) preg_replace('/[^\p{L}\p{N}]+/u', '', (string) $answer));

        if (mb_strlen($answer) < 2) {
            return [];
        }

        $words = $this->words($clue);

        if ($this->containsWholeAnswer($words, $answer)) {
            return [$this->error('answer_in_clue', 'Clue contains the answer')];
        }

        $answerRoot = $this->root($answer);

        foreach ($words as $word) {
            if ($this->root($word) === $answerRoot && mb_strlen($answerRoot) >= 3) {
                return [$this->warning('answer_root_in_clue', "Clue word \"{$word}\" shares a root with the answer")];
            }
        }

        foreach ($words as $word) {
            if ($this->isPartOfAnswer($word, $answer)) {
                return [$this->warning('partial_answer_in_clue', "Clue word \"{$word}\" is part of the answer")];
            }
        }

        return [];
    }

    /**
     * The answer appears as a clue word, or spelled across consecutive clue
     * words for multi-word answers ("Ice cream" for ICECREAM).
     *
     * @param  list<string>  $words
     */
    private function containsWholeAnswer(array $words, string $answer): bool
    {
        foreach ($words as $start => $word) {
            $joined = '';

            for ($i = $start; $i < count($words) && mb_strlen($joined) < mb_strlen($answer); $i++) {
                $joined .= $words[$i];
            }

            if ($joined === $answer) {
                return true;
            }
        }

        return false;
    }

    /**
     * A clue word that forms one end of a compound answer (FLOWER in
     * SUNFLOWER), or an answer that forms one end of a compound clue word
     * (BALL in "baseball").
     */
    private function isPartOfAnswer(string $word, string $answer): bool
    {
        if (mb_strlen($word) < 4 || in_array($word, self::STOPWORDS, true)) {
            return false;
        }

        [$shorter, $longer] = mb_strlen($word) < mb_strlen($answer) ? [$word, $answer] : [$answer, $word];

        if (mb_strlen($shorter) < 4) {
            return false;
        }

        return str_starts_with($longer, $shorter) || str_ends_with($longer, $shorter);
    }

    /**
     * A crude stem: strips one common suffix, a doubled final consonant
     * (RUNN → RUN) and a trailing "e" (BAKE / BAKING → BAK).
     */
    private function root(string $word): string
    {
        foreach (self::SUFFIXES as $suffix) {
            if (str_ends_with($word, $suffix) && mb_strlen($word) - mb_strlen($suffix) >= 3) {
                $word = mb_substr($word, 0, -mb_strlen($suffix));

                break;
            }
        }

        if (preg_match('/([b-df-hj-np-tv-z])\1$/', $word)) {
            $word = mb_substr($word, 0, -1);
        }

        if (str_ends_with($word, 'e') && mb_strlen($word) > 3) {
            $word = mb_substr($word, 0, -1);
        }

        return $word;
    }

    /**
     * The clue reference text ("22-Across", "starred clues"), if any.
     */
    private function crossReference(string $clue): ?string
    {
        if (preg_match('/\b(\d+)\s*-?\s*(across|down)\b/i', $clue, $match)) {
            return $match[1].'-'.ucfirst(mb_strtolower($match[2]));
        }

        if (preg_match('/\bstarred\s+(clue|answer|entr(y|ie))s?\b/i', $clue, $match)) {
            return $match[0];
        }

        return null;
    }

    private function isPlaceholder(string $clue): bool
    {
        return (bool) preg_match('/^(?:todo|tbd|tk|xx+|clue|\?+|\.+|-+|_+)$/i', $clue)
            || (bool) preg_match('/\b(?:todo|tbd|lorem ipsum)\b|\?\?\?/i', $clue);
    }

    private function hasUnbalancedPunctuation(string $clue): bool
    {
        $pairs = ['(' => ')', '[' => ']', '{' => '}', '“' => '”'];

        foreach ($pairs as $open => $close) {
            if (mb_substr_count($clue, $open) !== mb_substr_count($clue, $close)) {
                return true;
            }
        }

        return mb_substr_count($clue, '"') % 2 !== 0;
    }

    /**
     * @return list<string>
     */
    private function words(string $clue): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($clue), -1, PREG_SPLIT_NO_EMPTY);

        return $words === false ? [] : $words;
    }

    /**
     * @return ClueIssue
     */
    private function error(string $code, string $message): array
    {
        return ['code' => $code, 'severity' => self::SEVERITY_ERROR, 'message' => $message];
    }

    /**
     * @return ClueIssue
     */
    private function warning(string $code, string $message): array
    {
        return ['code' => $code, 'severity' => self::SEVERITY_WARNING, 'message' => $message];
    }
}
