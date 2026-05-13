@extends('errors.layout', ['title' => __('Page expired')])

@section('error-body')
    <p class="code">419 · {{ __('Page expired') }}</p>
    <h1>{{ __('Your session timed out.') }}</h1>
    <p class="lede">
        {{ __('Refresh the page and try again. If you were submitting a form, you may need to fill it in once more.') }}
    </p>
    <div class="actions">
        <a href="javascript:window.location.reload()" class="btn btn-primary">{{ __('Reload page') }}</a>
        <a href="{{ url('/') }}" class="btn btn-ghost">{{ __('Back to home') }}</a>
    </div>
@endsection
