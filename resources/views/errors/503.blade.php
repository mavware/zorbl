@extends('errors.layout', ['title' => __('Maintenance')])

@section('error-body')
    <p class="code">503 · {{ __('Down for maintenance') }}</p>
    <h1>{{ __("We're shipping something good.") }}</h1>
    <p class="lede">
        @if (! empty($exception?->getMessage()))
            {{ $exception->getMessage() }}
        @else
            {{ __(":app is briefly offline for a deploy. Check back in a couple of minutes — we'll be right back.", ['app' => config('app.name')]) }}
        @endif
    </p>
    <div class="actions">
        <a href="javascript:window.location.reload()" class="btn btn-primary">{{ __('Try again') }}</a>
    </div>
@endsection
