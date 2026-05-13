@extends('errors.layout', ['title' => __('Too many requests')])

@section('error-body')
    <p class="code">429 · {{ __('Too many requests') }}</p>
    <h1>{{ __('Slow down a moment.') }}</h1>
    <p class="lede">
        {{ __("You've made a lot of requests in a short window. Wait a minute and try again — if you think this was a mistake, contact support.") }}
    </p>
    <div class="actions">
        <a href="{{ url('/') }}" class="btn btn-primary">{{ __('Back to home') }}</a>
    </div>
@endsection
