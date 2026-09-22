@props([
    'title' => null,
    'description' => '',
    'canonical' => null,
    'image' => null,
    'noindex' => false,
])

@php
    // Query-string variants (pagination, sort, filter, tracking params) are
    // duplicates of the clean URL. Keep them out of the index but let their
    // links be followed. They deliberately carry no canonical: pairing noindex
    // with a canonical that points elsewhere sends Google conflicting signals
    // and can leak the noindex onto the clean URL.
    $isQueryVariant = (string) request()->getQueryString() !== '';
    $noindex = $noindex || $isQueryVariant;
    $canonicalUrl = $canonical ?? url()->current();
    $ogTitle = ($title ? $title.' — ' : '').config('app.name');
    $ogImage = $image ?? asset('og-default.png');
@endphp

@push('head_meta')
    @if ($noindex)
        <meta name="robots" content="noindex, follow">
    @endif
    @unless ($isQueryVariant)
        <link rel="canonical" href="{{ $canonicalUrl }}">
    @endunless
    @if ($description !== '')
        <meta name="description" content="{{ $description }}">
    @endif
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:title" content="{{ $ogTitle }}">
    @if ($description !== '')
        <meta property="og:description" content="{{ $description }}">
    @endif
    <meta property="og:image" content="{{ $ogImage }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $ogTitle }}">
    @if ($description !== '')
        <meta name="twitter:description" content="{{ $description }}">
    @endif
    <meta name="twitter:image" content="{{ $ogImage }}">
@endpush
