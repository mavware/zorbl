{{-- Inline so the mark follows the theme's --logo-* tokens; the favicon uses the static file from Theme::logoFile(). --}}
<svg viewBox="0 0 32 32" role="img" aria-label="{{ config('app.name') }}" data-app-logo {{ $attributes }}>
    <rect width="32" height="32" style="rx: var(--logo-radius); fill: var(--logo-fill);"></rect>
    <text x="16" y="23" text-anchor="middle" style="fill: var(--logo-ink); font-family: var(--logo-font); font-weight: var(--logo-weight); font-size: 20px;">CB</text>
</svg>
