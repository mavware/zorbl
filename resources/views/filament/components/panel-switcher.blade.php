@php
    $adminUrl = filament()->getUrl();
    $userUrl = route('crosswords.index');
@endphp

<nav
    data-test="panel-switcher"
    aria-label="{{ __('Switch between admin and user views') }}"
    class="fi-panel-switcher"
    style="display: inline-flex; align-items: center; margin-inline-start: 0.75rem; padding: 3px; border-radius: 9999px; background: color-mix(in srgb, var(--gray-500) 18%, transparent); font-size: 0.75rem; font-weight: 600; line-height: 1;"
>
    <a
        href="{{ $adminUrl }}"
        aria-current="page"
        style="display: inline-flex; align-items: center; padding: 0.375rem 0.75rem; border-radius: 9999px; background: var(--primary-500); color: var(--gray-950); text-decoration: none;"
    >
        {{ __('Admin') }}
    </a>
    <a
        href="{{ $userUrl }}"
        style="display: inline-flex; align-items: center; padding: 0.375rem 0.75rem; border-radius: 9999px; color: var(--gray-500); text-decoration: none;"
    >
        {{ __('User') }}
    </a>
</nav>
