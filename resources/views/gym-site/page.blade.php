@php
    $pages = ['home' => 'Home', 'about' => 'About', 'plans' => 'Plans', 'facilities' => 'Facilities', 'announcements' => 'Announcements', 'contact' => 'Contact'];
@endphp
<!DOCTYPE html>
<html lang="en" class="scroll-smooth motion-reduce:scroll-auto">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @if($preview)<meta name="robots" content="noindex, nofollow">@endif
    <title>{{ $pageTitle }} | {{ $gymName }}</title>
    <meta name="description" content="{{ $description }}">
    <link rel="canonical" href="{{ $canonical }}">
    <meta property="og:title" content="{{ $pageTitle }} | {{ $gymName }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ $canonical }}">
    @if($logoUrl)<link rel="icon" type="image/webp" href="{{ $logoUrl }}">@endif
    @fonts
    @vite('resources/css/app.css')
</head>
<body class="gym-public bg-white font-sans text-slate-950">
    @if($preview && !$embedded)<div class="border-b bg-muted px-4 py-3 text-sm">Draft preview — changes are not public. <a class="inline-flex min-h-11 items-center underline" href="{{ route('website.edit') }}">Return to website editor</a></div>@endif
    <a href="#main-content" class="sr-only z-50 rounded bg-primary px-4 py-3 text-primary-foreground focus:not-sr-only focus:absolute focus:top-2 focus:left-2">Skip to content</a>
    <header class="sticky top-0 z-40 border-b bg-background/95 backdrop-blur">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
            <a href="#home" class="flex min-h-11 min-w-0 items-center gap-3 font-semibold focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ring">
                @if($logoUrl)<img src="{{ $logoUrl }}" alt="" width="44" height="44" class="size-11 shrink-0 rounded-lg object-contain">@else<span class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-primary text-primary-foreground"><x-icon name="dumbbell" class="size-6" aria-hidden="true" /></span>@endif
                <span class="min-w-0 break-words">{{ $gymName }}</span>
            </a>
            <nav aria-label="Main navigation" class="hidden shrink-0 items-center gap-1 lg:flex">
                @foreach ($pages as $key => $label)
                    <a href="#{{ $key }}" class="flex min-h-11 items-center rounded-md px-3 text-sm font-medium hover:bg-muted focus-visible:outline-2 focus-visible:outline-ring">{{ $label }}</a>
                @endforeach
                @if($membershipCheckUrl)<a href="{{ $membershipCheckUrl }}" class="ml-2 flex min-h-11 items-center rounded-md border px-4 text-sm font-medium hover:bg-muted focus-visible:outline-2 focus-visible:outline-ring">Check membership</a>@endif
            </nav>
            <details class="relative shrink-0 lg:hidden">
                <summary class="flex min-h-11 cursor-pointer items-center rounded-md border px-3 font-medium focus-visible:outline-2 focus-visible:outline-ring">Menu</summary>
                <nav aria-label="Mobile navigation" class="absolute top-full right-0 z-20 mt-2 w-56 space-y-1 rounded-xl border bg-card p-2 shadow-lg">
                    @foreach ($pages as $key => $label)
                        <a href="#{{ $key }}" class="flex min-h-11 items-center rounded-md px-3 text-sm font-medium hover:bg-muted focus-visible:outline-2 focus-visible:outline-ring">{{ $label }}</a>
                    @endforeach
                    @if($membershipCheckUrl)<a href="{{ $membershipCheckUrl }}" class="flex min-h-11 items-center rounded-md border px-3 text-sm font-medium hover:bg-muted focus-visible:outline-2 focus-visible:outline-ring">Check membership</a>@endif
                </nav>
            </details>
        </div>
    </header>
    <main id="main-content" tabindex="-1">
        <section id="home" aria-labelledby="home-title" class="relative isolate scroll-mt-24 overflow-hidden bg-slate-950 text-white">
            @if($imageUrl)<img src="{{ $imageUrl }}" alt="{{ $imageAlt }}" width="1600" height="1200" class="absolute inset-0 -z-20 h-full w-full object-cover">@endif
            <div aria-hidden="true" class="absolute inset-0 -z-10 bg-gradient-to-r from-black/95 via-black/75 to-black/40"></div>
            <div class="mx-auto flex min-h-[32rem] max-w-6xl items-center px-5 py-16 sm:px-8 sm:py-24">
                <div class="max-w-2xl space-y-6">
                    <p class="text-sm font-semibold tracking-wider text-red-400 uppercase">Welcome to {{ $gymName }}</p>
                    <h1 id="home-title" aria-label="Make space for your next workout." class="text-4xl leading-tight font-bold tracking-tight break-words sm:text-6xl">Make space for your next <span class="text-red-500">workout.</span></h1>
                    @if ($description)<p class="max-w-xl text-base leading-7 text-white/85 sm:text-lg">{{ $description }}</p>@endif
                    <div class="flex flex-wrap gap-3">
                        <a href="#plans" class="flex min-h-11 items-center rounded-lg bg-primary px-5 font-medium text-primary-foreground hover:bg-primary/90 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ring">Explore plans</a>
                        <a href="#contact" class="flex min-h-11 items-center rounded-lg border border-white/70 px-5 font-medium hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-white">Contact the gym</a>
                    </div>
                </div>
            </div>
        </section>
        <section id="about" aria-labelledby="about-title" class="scroll-mt-24 border-t bg-muted/30">
            <div class="mx-auto max-w-6xl space-y-6 px-4 py-14 sm:px-6 sm:py-20">
                <div class="space-y-2 text-center"><p class="text-xs font-semibold tracking-widest text-primary uppercase">Get to know us</p><h2 id="about-title" class="text-2xl font-bold tracking-tight sm:text-3xl">About the gym</h2></div>
                @if($aboutHtml !== null)
                    {{-- WebsiteRichText rebuilds allowlisted elements without attributes at save and render. --}}
                    <div class="mx-auto max-w-3xl space-y-4 leading-7 break-words [&_ul]:list-inside [&_ul]:list-disc [&_ol]:list-inside [&_ol]:list-decimal [&_h2]:text-xl [&_h3]:text-lg [&_blockquote]:border-l-2 [&_blockquote]:pl-4">{!! $aboutHtml !!}</div>
                @else
                    <p class="mx-auto max-w-3xl text-center leading-7 break-words whitespace-pre-line">{{ $about ?: 'The gym has not added an introduction yet.' }}</p>
                @endif
            </div>
        </section>
        @foreach (['plans' => 'Membership plans', 'facilities' => 'Facilities', 'announcements' => 'Announcements'] as $section => $title)
            <section id="{{ $section }}" aria-labelledby="{{ $section }}-title" class="scroll-mt-24 border-t {{ $section === 'facilities' ? 'bg-muted/30' : '' }}">
                <div class="mx-auto max-w-6xl space-y-6 px-4 py-14 sm:px-6 sm:py-20">
                    <h2 id="{{ $section }}-title" class="text-center text-2xl font-bold tracking-tight sm:text-3xl">{{ $title }}</h2>
                    @if ($section === 'plans')<p class="text-sm text-muted-foreground">Prices are in Philippine pesos. Contact the gym about current availability, inclusions, and tax details.</p>@endif
                    @if ($section === 'plans' && $plansMode === 'image' && $plansImageUrl)
                        <figure class="mx-auto max-w-4xl space-y-3">
                            <img src="{{ $plansImageUrl }}" alt="{{ $plansAlt }}" loading="lazy" class="h-auto w-full rounded-2xl border border-slate-200">
                            <figcaption><a href="{{ $plansImageUrl }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center text-sm underline underline-offset-4">View full-size plans image (opens a new tab)</a></figcaption>
                        </figure>
                    @else
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @forelse ($lists[$section] as $item)
                            <article class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                                <span class="flex size-10 items-center justify-center rounded-xl bg-red-50 text-primary"><x-icon name="{{ $section === 'plans' ? 'wallet' : ($section === 'facilities' ? 'dumbbell' : 'clock') }}" class="size-5" aria-hidden="true" /></span>
                                <h3 class="text-lg font-semibold break-words">{{ $item[$section === 'announcements' ? 'title' : 'name'] ?? '' }}</h3>
                                @if ($section === 'plans')
                                    <p class="text-3xl font-bold tracking-tight">₱{{ number_format(((int) ($item['price_centavos'] ?? 0)) / 100, 2) }}</p>
                                    <p class="text-sm text-muted-foreground">{{ $item['duration'] ?? '' }}</p>
                                @endif
                                @if ($section === 'announcements' && !empty($item['date']))<time class="block text-sm text-muted-foreground" datetime="{{ $item['date'] }}">{{ $item['date'] }}</time>@endif
                                <p class="text-sm leading-6 break-words whitespace-pre-line">{{ $item[$section === 'announcements' ? 'body' : 'description'] ?? '' }}</p>
                            </article>
                        @empty
                            <p class="text-muted-foreground">No {{ strtolower($title) }} have been published yet.</p>
                        @endforelse
                    </div>
                    @if ($lists[$section]->hasPages())
                        <nav aria-label="{{ $title }} pagination" class="flex items-center gap-3">
                            @if($lists[$section]->previousPageUrl())<a href="{{ $lists[$section]->previousPageUrl() }}" class="flex min-h-11 items-center rounded-md border px-4">Previous</a>@endif
                            <span class="text-sm">{{ $lists[$section]->currentPage() }} / {{ $lists[$section]->lastPage() }}</span>
                            @if($lists[$section]->nextPageUrl())<a href="{{ $lists[$section]->nextPageUrl() }}" class="flex min-h-11 items-center rounded-md border px-4">Next</a>@endif
                        </nav>
                    @endif
                    @endif
                </div>
            </section>
        @endforeach
        <section id="contact" aria-labelledby="contact-title" class="scroll-mt-24 border-t bg-muted/30">
            <div class="mx-auto max-w-6xl space-y-6 px-4 py-14 sm:px-6 sm:py-20">
                <h2 id="contact-title" class="text-center text-2xl font-bold tracking-tight sm:text-3xl">Contact the gym</h2>
                <dl class="grid gap-6 rounded-2xl border border-slate-200 bg-white p-6 sm:grid-cols-2">
                    @foreach (['address' => 'Address', 'hours' => 'Opening hours', 'phone' => 'Phone', 'email' => 'Email'] as $key => $label)
                        @if (!empty($contact[$key]))
                            <div><dt class="mb-2 font-semibold">{{ $label }}</dt><dd class="break-words whitespace-pre-line">
                                @if ($key === 'email' && filter_var($contact[$key], FILTER_VALIDATE_EMAIL))
                                    <a class="inline-flex min-h-11 items-center underline" href="mailto:{{ $contact[$key] }}">{{ $contact[$key] }}</a>
                                @elseif ($key === 'phone' && preg_match('/^\+?[0-9]{7,15}$/', preg_replace('/[\s().-]/', '', $contact[$key])))
                                    <a class="inline-flex min-h-11 items-center underline" href="tel:{{ preg_replace('/[\s().-]/', '', $contact[$key]) }}">{{ $contact[$key] }}</a>
                                @else
                                    {{ $contact[$key] }}
                                @endif
                            </dd></div>
                        @endif
                    @endforeach
                    @if (!array_filter($contact))<div><dt class="sr-only">Contact details</dt><dd>No contact details have been published yet.</dd></div>@endif
                </dl>
                @if($contactFormEnabled) @include('gym-site.inquiry-form') @endif
                @include('gym-site.privacy')
            </div>
        </section>
    </main>
    <footer class="border-t"><div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-4 py-6 text-sm sm:px-6"><p class="break-words">{{ $gymName }}</p><a href="#privacy" data-open-privacy class="flex min-h-11 items-center underline underline-offset-4">Privacy notice</a><a href="#home" class="flex min-h-11 items-center underline underline-offset-4">Back to top</a></div></footer>
    <script>
        // Keep the small-screen menu out of the way after selecting a section.
        document.querySelectorAll('details nav a').forEach(function (link) {
            link.addEventListener('click', function () { link.closest('details').open = false; });
        });
        document.querySelectorAll('[data-open-privacy]').forEach(function (link) {
            link.addEventListener('click', function () { document.getElementById('privacy').open = true; });
        });
    </script>
</body>
</html>
