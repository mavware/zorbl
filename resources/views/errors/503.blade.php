@extends('errors.layout', ['title' => __('Maintenance')])

@section('error-body')
    <p class="code">503 · {{ __('Down for maintenance') }}</p>
    <h1>{{ __("We're shipping something good.") }}</h1>
    <p class="lede">
        @if (! empty($exception?->getMessage()))
            {{ $exception->getMessage() }}
        @else
            {{ __("Zorbl is briefly offline for a deploy. Check back in a couple of minutes — we'll be right back.") }}
        @endif
    </p>
    <div class="actions">
        <a href="javascript:window.location.reload()" class="btn btn-primary">{{ __('Try again') }}</a>
    </div>
@endsection
