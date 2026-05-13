@extends('errors.layout', ['title' => __('Server error')])

@section('error-body')
    <p class="code">500 · {{ __('Server error') }}</p>
    <h1>{{ __('Something went sideways on our end.') }}</h1>
    <p class="lede">
        {{ __("We've been notified and are looking at it. In the meantime, in-progress solves are saved locally and will sync once everything's back.") }}
    </p>
    <div class="actions">
        <a href="{{ url('/') }}" class="btn btn-primary">{{ __('Back to home') }}</a>
        <a href="javascript:window.location.reload()" class="btn btn-ghost">{{ __('Try again') }}</a>
    </div>
@endsection
