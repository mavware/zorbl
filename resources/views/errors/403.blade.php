@extends('errors.layout', ['title' => __('Forbidden')])

@section('error-body')
    <p class="code">403 · {{ __('Forbidden') }}</p>
    <h1>{{ __("You're not allowed to see this.") }}</h1>
    <p class="lede">
        {{ __("This page requires permission you don't have on your account. If you think that's wrong, contact support.") }}
    </p>
    <div class="actions">
        <a href="{{ url('/') }}" class="btn btn-primary">{{ __('Back to home') }}</a>
        @auth
            <a href="{{ route('support.create') }}" class="btn btn-ghost">{{ __('Contact support') }}</a>
        @else
            <a href="{{ route('login') }}" class="btn btn-ghost">{{ __('Log in') }}</a>
        @endauth
    </div>
@endsection
