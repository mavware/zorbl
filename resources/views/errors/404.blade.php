@extends('errors.layout', ['title' => __('Page not found')])

@section('error-body')
    <p class="code">404 · {{ __('Not found') }}</p>
    <h1>{{ __("This puzzle's missing a few squares.") }}</h1>
    <p class="lede">
        {{ __('The page you were after doesn\'t exist — or has been unpublished by its constructor.') }}
    </p>
    <div class="actions">
        <a href="{{ url('/') }}" class="btn btn-primary">{{ __('Back to home') }}</a>
        <a href="{{ url('/puzzles') }}" class="btn btn-ghost">{{ __('Browse puzzles') }}</a>
    </div>
@endsection
