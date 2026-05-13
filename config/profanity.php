<?php

/*
 * Words and phrases blocked by App\Support\ProfanityFilter. Matched as whole
 * words (case-insensitive). Edit this list to reflect your moderation policy.
 *
 * Keep entries lowercase. Multi-word phrases work too. For project-specific
 * additions you'd rather not commit to source control, set the env var
 * PROFANITY_EXTRA_WORDS to a comma-separated list — those are merged with the
 * defaults at runtime.
 *
 * NOTE: This is intentionally a short starter list focused on the strongest,
 * most universally-blocked English profanity. You should review and extend it
 * to match your own moderation policy and the languages you serve.
 */

return [
    'words' => array_values(array_filter(array_unique(array_merge(
        [
            'fuck',
            'shit',
            'asshole',
            'bitch',
            'bastard',
            'dickhead',
            'motherfucker',
            'cunt',
        ],
        array_map('trim', array_filter(explode(',', (string) env('PROFANITY_EXTRA_WORDS', '')))),
    )))),
];
