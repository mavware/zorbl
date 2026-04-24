<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $crossword->title ?? 'Crossword' }} - Zorbl</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/embed.css', 'resources/js/embed.js'])
</head>
<body class="m-0 bg-white p-2">
    <div
        data-zorbl-embed
        data-crossword-id="{{ $crossword->id }}"
        data-api-url="{{ url('/api/embed') }}/"
    >
        <p class="text-sm text-zinc-500">Loading puzzle...</p>
    </div>
    <div class="mt-2 text-center text-xs text-zinc-500">
        <a href="{{ url('/') }}" target="_blank" rel="noopener" class="hover:text-blue-500 transition-colors">
            Powered by Zorbl
        </a>
    </div>
</body>
</html>
