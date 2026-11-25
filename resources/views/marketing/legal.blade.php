<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $page['title'] }} — {{ config('app.name') }}</title>
    @if ($legal['draft'])<meta name="robots" content="noindex, nofollow">@endif
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-background text-foreground antialiased">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:z-50 focus:bg-background focus:p-4">Skip to content</a>
    <header class="border-b border-border"><nav aria-label="Main" class="mx-auto flex max-w-3xl flex-wrap gap-4 px-4 py-4"><a href="{{ route('home') }}" class="inline-flex min-h-11 items-center font-semibold">{{ config('app.name') }}</a><a href="{{ route('login') }}" class="inline-flex min-h-11 items-center">Log in</a></nav></header>
    <main id="main-content" tabindex="-1" class="mx-auto max-w-3xl space-y-6 px-4 py-8">
        <h1 class="text-3xl font-semibold">{{ $page['title'] }}</h1>
        <p class="text-sm text-muted-foreground">Policy version {{ $legal['label'] }}</p>
        @if ($legal['draft'])<p class="rounded-md border border-border p-4">Draft for local review. Operator details and commercial terms must be completed before launch.</p>@endif
        @foreach ($page['sections'] as $section)
            <section class="space-y-3"><h2 class="text-xl font-semibold">{{ $section['title'] }}</h2><p class="whitespace-pre-line break-words leading-relaxed">{{ $section['text'] }}</p></section>
        @endforeach
    </main>
    @include('marketing.legal-footer')
</body>
</html>
