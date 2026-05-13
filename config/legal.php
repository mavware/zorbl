<?php

return [
    /*
     * Legal entity that operates the service. Replace with the registered
     * company name once you've incorporated. Used in Terms, Privacy, etc.
     */
    'entity' => env('LEGAL_ENTITY', config('app.name')),

    /*
     * General legal / privacy contact address. Used in all legal pages.
     */
    'contact_email' => env('LEGAL_CONTACT_EMAIL', 'legal@'.parse_url((string) env('APP_URL', 'https://example.com'), PHP_URL_HOST)),

    /*
     * DMCA / copyright-takedown contact address.
     */
    'dmca_email' => env('LEGAL_DMCA_EMAIL', env('LEGAL_CONTACT_EMAIL', 'dmca@'.parse_url((string) env('APP_URL', 'https://example.com'), PHP_URL_HOST))),

    /*
     * Governing-law jurisdiction. Pick the state/country the entity is
     * registered in. Defaults to a placeholder so launch isn't blocked.
     */
    'governing_law' => env('LEGAL_GOVERNING_LAW', 'the State of Delaware, United States of America'),

    /*
     * Effective date for the current published versions of Terms / Privacy /
     * Cookies. Bump this whenever you make a material change, and add a
     * notice to existing users.
     */
    'effective_date' => env('LEGAL_EFFECTIVE_DATE', '2026-05-12'),

    /*
     * Minimum age to register. 13 keeps the service out of the strictest
     * COPPA regime; 16 keeps it out of the strictest GDPR regime in
     * jurisdictions that have set the digital-consent age there.
     */
    'minimum_age' => env('LEGAL_MINIMUM_AGE', 13),
];
