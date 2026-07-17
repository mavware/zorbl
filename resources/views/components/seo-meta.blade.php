@props([
    'title' => null,
    'description' => '',
    'canonical' => null,
    'image' => null,
])

@php
    $canonicalUrl = $canonical ?? url()->current();
    $ogTitle = ($title ? $title.' — ' : '').config('app.name');
    $ogImage = $image ?? asset('android-chrome-512x512.png');
@endphp

@push('head_meta')
    <link rel="canonical" href="{{ $canonicalUrl }}">
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
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="{{ $ogTitle }}">
    @if ($description !== '')
        <meta name="twitter:description" content="{{ $description }}">
    @endif
    <meta name="twitter:image" content="{{ $ogImage }}">
@endpush
