@php
    $appName = config('app.name');
    $description = 'Gym management software for Philippine gyms: members, memberships, QR attendance, payments, and your own gym website — in one place.';

    $features = [
        ['icon' => 'users', 'title' => 'Members & memberships', 'text' => 'Register members, assign plans, and see at a glance who is active, expiring soon, or expired.'],
        ['icon' => 'qr-code', 'title' => 'QR check-in', 'text' => 'Members scan their QR at the front desk. Attendance is recorded with the time — no logbook needed.'],
        ['icon' => 'wallet', 'title' => 'Payment tracking', 'text' => 'Record cash, GCash, and bank payments, see unpaid members, and get daily and monthly totals.'],
        ['icon' => 'globe', 'title' => 'Your own gym website', 'text' => 'Publish your plans, photos, hours, and contact details on a branded site you update yourself.'],
        ['icon' => 'smartphone', 'title' => 'Member portal', 'text' => 'Members check their plan and expiration date from their phone, without calling the front desk.'],
        ['icon' => 'chart-column', 'title' => 'Workouts & reports', 'text' => 'Assign routines to members and track attendance, renewals, and revenue over time.'],
    ];

    $steps = [
        ['title' => 'Create your gym', 'text' => 'Request access, accept your invitation, and add your gym name and contact details.'],
        ['title' => 'Add plans and members', 'text' => 'Set your membership plans and prices, then register your members.'],
        ['title' => 'Start checking in', 'text' => 'Print member QR codes and scan them at the front desk with a phone or scanner.'],
    ];

    // Prices and limits come from config/plans.php (single source of truth).
    $plans = config('plans.plans');
    $comparison = config('plans.comparison');
    $monthsCharged = config('plans.yearly_months_charged');
    $peso = fn (int $amount) => '₱'.number_format($amount);

    // Sign-up is invite-only: owners send the "Request access" form, we email them an access link.
    $accessEmail = config('platform.access_email');
    $requestAccess = fn (?string $planKey = null) => route('access-requests.create', $planKey ? ['plan' => $planKey] : []);

    $faqs = [
        ['q' => 'How do I sign up?', 'a' => 'Tap "Request access" and tell us your gym name and plan (or email '.$accessEmail.'). We\'ll send you a link to set your password and create your gym. You can try the demo anytime — no sign-up needed.'],
        ['q' => 'Do I need special hardware?', 'a' => 'No. Staff can scan QR codes with a phone or laptop camera. A USB QR scanner also works if you prefer one.'],
        ['q' => 'Can other gyms see my members?', 'a' => 'No. Each gym\'s data is kept separate, and staff can only see the gym they belong to.'],
        ['q' => 'Can members pay online?', 'a' => 'Yes, on Pro and Multi-Branch: members can pay with GCash, Maya, or card. On Starter, you record the payments you receive (cash, GCash transfer, bank).'],
        ['q' => 'Is there a discount for paying yearly?', 'a' => 'Yes. Pay for '.$monthsCharged.' months and get 12 — that\'s '.(12 - $monthsCharged).' months free, paid once for the whole year.'],
        ['q' => 'What happens to my data if I cancel?', 'a' => 'Contact the operator about account closure and data requests. Retention and cancellation details are described in the privacy and refund policies.'],
    ];
@endphp
<!DOCTYPE html>
<html lang="en" class="dark scroll-smooth motion-reduce:scroll-auto">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $appName }} — Gym management software for Philippine gyms</title>
        <meta name="description" content="{{ $description }}">
        <link rel="canonical" href="{{ url('/') }}">

        {{-- Link previews on Facebook / Messenger --}}
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ $appName }}">
        <meta property="og:title" content="{{ $appName }} — Run your gym, not paperwork">
        <meta property="og:description" content="{{ $description }}">
        <meta property="og:url" content="{{ url('/') }}">
        <meta name="theme-color" content="#0a0a0a">

        <link rel="icon" href="/favicon.ico?v=clean-logo-2" sizes="any">
        <link rel="icon" href="/favicon.svg?v=clean-logo-2" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png?v=clean-logo-2">

        @fonts
        @vite('resources/css/app.css')
    </head>
    <body class="bg-background font-sans text-foreground antialiased">
        <a href="#main" class="sr-only rounded-md bg-primary px-4 py-2 text-primary-foreground focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-50">Skip to main content</a>

        {{-- Header --}}
        <header class="sticky top-0 z-40 border-b border-border/60 bg-background/90 backdrop-blur">
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6">
                <a href="{{ route('home') }}" class="flex items-center gap-2 font-semibold tracking-tight">
                    <span class="flex size-8 items-center justify-center rounded-md bg-primary text-primary-foreground">
                        <x-icon name="dumbbell" class="size-5" />
                    </span>
                    <span class="whitespace-nowrap">{{ $appName }}</span>
                </a>

                <nav aria-label="Main" class="hidden items-center gap-6 text-sm text-muted-foreground md:flex">
                    <a href="#features" class="hover:text-foreground">Features</a>
                    <a href="#how-it-works" class="hover:text-foreground">How it works</a>
                    <a href="#pricing" class="hover:text-foreground">Pricing</a>
                    <a href="#faq" class="hover:text-foreground">FAQ</a>
                </nav>

                {{-- Desktop actions --}}
                <div class="hidden items-center gap-2 text-sm md:flex">
                    @auth
                        <a href="{{ route('dashboard') }}" class="inline-flex h-10 items-center rounded-md bg-primary px-4 font-medium text-primary-foreground hover:bg-primary/90">Go to dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="inline-flex h-10 items-center rounded-md px-3 font-medium hover:bg-accent">Log in</a>
                        <form method="POST" action="{{ route('demo.store') }}">
                            @csrf
                            <button type="submit" class="inline-flex h-10 cursor-pointer items-center rounded-md bg-primary px-4 font-medium text-primary-foreground hover:bg-primary/90">Try demo</button>
                        </form>
                    @endauth
                </div>

                {{-- Mobile burger menu: native <details>, works without JavaScript --}}
                <details data-mobile-menu class="group md:hidden">
                    <summary class="flex size-11 cursor-pointer list-none items-center justify-center rounded-md hover:bg-accent [&::-webkit-details-marker]:hidden">
                        <span class="sr-only">Menu</span>
                        <x-icon name="menu" class="size-6 group-open:hidden" />
                        <x-icon name="x" class="hidden size-6 group-open:block" />
                    </summary>

                    <div class="absolute inset-x-0 top-16 border-b border-border bg-background px-4 pt-2 pb-4 shadow-xl">
                        <nav aria-label="Mobile" class="flex flex-col">
                            <a href="#features" class="flex h-11 items-center rounded-md px-3 hover:bg-accent">Features</a>
                            <a href="#how-it-works" class="flex h-11 items-center rounded-md px-3 hover:bg-accent">How it works</a>
                            <a href="#pricing" class="flex h-11 items-center rounded-md px-3 hover:bg-accent">Pricing</a>
                            <a href="#faq" class="flex h-11 items-center rounded-md px-3 hover:bg-accent">FAQ</a>
                        </nav>
                        <div class="mt-3 grid gap-2 border-t border-border pt-3">
                            @auth
                                <a href="{{ route('dashboard') }}" class="flex h-11 items-center justify-center rounded-md bg-primary font-medium text-primary-foreground">Go to dashboard</a>
                            @else
                                <a href="{{ route('login') }}" class="flex h-11 items-center justify-center rounded-md border border-border font-medium">Log in</a>
                                <form method="POST" action="{{ route('demo.store') }}">
                                    @csrf
                                    <button type="submit" class="flex h-11 w-full cursor-pointer items-center justify-center rounded-md bg-primary font-medium text-primary-foreground">Try demo</button>
                                </form>
                            @endauth
                        </div>
                    </div>
                </details>
            </div>
        </header>
        {{-- Close the mobile menu after tapping a section link (tiny inline enhancement, no download) --}}
        <script>
            document.querySelectorAll('[data-mobile-menu] nav a').forEach((link) =>
                link.addEventListener('click', () => link.closest('details').removeAttribute('open')),
            );
        </script>

        @if (session('status'))
            <p role="status" class="border-b border-primary/30 bg-primary/10 px-4 py-3 text-center text-sm">{{ session('status') }}</p>
        @endif

        <main id="main">
            {{-- Hero --}}
            <section class="relative overflow-hidden">
                <div aria-hidden="true" class="pointer-events-none absolute inset-x-0 -top-40 mx-auto h-80 max-w-3xl rounded-full bg-primary/25 blur-3xl"></div>

                <div class="relative mx-auto grid max-w-6xl items-center gap-12 px-4 py-20 sm:px-6 lg:grid-cols-2 lg:py-28">
                    <div>
                        <p class="mb-4 inline-flex items-center gap-2 rounded-full border border-border px-3 py-1 text-xs font-medium text-muted-foreground">
                            <x-icon name="map-pin" class="size-3.5 text-primary" />
                            Made for gyms in the Philippines
                        </p>
                        <h1 class="text-4xl font-bold tracking-tight text-balance sm:text-5xl lg:text-6xl">
                            Run your gym, <span class="text-primary">not paperwork.</span>
                        </h1>
                        <p class="mt-6 max-w-xl text-lg text-pretty text-muted-foreground">
                            Members, memberships, QR check-in, payments, and your own gym website — managed from one simple dashboard on your phone or computer.
                        </p>
                        <div class="mt-8 flex flex-wrap gap-3">
                            <a href="{{ $requestAccess() }}" class="inline-flex h-11 items-center gap-2 rounded-md bg-primary px-6 font-medium text-primary-foreground hover:bg-primary/90">
                                Request access
                                <x-icon name="arrow-right" class="size-4" />
                            </a>
                            <a href="#features" class="inline-flex h-11 items-center rounded-md border border-border px-6 font-medium hover:bg-accent">See features</a>
                        </div>
                    </div>

                    {{-- Product preview built with HTML/CSS (no image download) --}}
                    <figure class="rounded-xl border border-border bg-card p-4 shadow-2xl shadow-primary/10 sm:p-6">
                        <figcaption class="sr-only">Preview of the members dashboard with sample data</figcaption>
                        <div class="mb-4 flex items-center justify-between">
                            <div>
                                <p class="text-sm font-semibold">Members</p>
                                <p class="text-xs text-muted-foreground">Sample data</p>
                            </div>
                            <span class="inline-flex items-center gap-1.5 rounded-md bg-primary px-3 py-1.5 text-xs font-medium text-primary-foreground">
                                <x-icon name="qr-code" class="size-3.5" />
                                Scan QR
                            </span>
                        </div>

                        <div class="mb-4 grid grid-cols-3 gap-2 text-center">
                            <div class="rounded-lg bg-muted p-3"><p class="text-xl font-bold">128</p><p class="text-xs text-muted-foreground">Active</p></div>
                            <div class="rounded-lg bg-muted p-3"><p class="text-xl font-bold">9</p><p class="text-xs text-muted-foreground">Expiring</p></div>
                            <div class="rounded-lg bg-muted p-3"><p class="text-xl font-bold">14</p><p class="text-xs text-muted-foreground">Expired</p></div>
                        </div>

                        <ul class="divide-y divide-border text-sm">
                            @foreach ([
                                ['Juan Dela Cruz', 'Monthly', 'Active', 'bg-emerald-500/15 text-emerald-400'],
                                ['Maria Santos', 'Quarterly', 'Expiring soon', 'bg-amber-500/15 text-amber-300'],
                                ['Pedro Reyes', 'Monthly', 'Expired', 'bg-primary/15 text-red-300'],
                            ] as [$member, $plan, $status, $badge])
                                <li class="flex items-center justify-between gap-3 py-2.5">
                                    <div class="min-w-0">
                                        <p class="truncate font-medium">{{ $member }}</p>
                                        <p class="text-xs text-muted-foreground">{{ $plan }}</p>
                                    </div>
                                    <span class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badge }}">● {{ $status }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </figure>
                </div>
            </section>

            {{-- Features --}}
            <section id="features" class="scroll-mt-16 border-t border-border/60 bg-card/40">
                <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6">
                    <div class="max-w-2xl">
                        <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">Everything your front desk needs</h2>
                        <p class="mt-4 text-muted-foreground">Replace the logbook, spreadsheets, and scattered chat messages with one system your whole team can use.</p>
                    </div>

                    <ul class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($features as $feature)
                            <li class="rounded-xl border border-border bg-background p-6 transition-colors hover:border-primary/60">
                                <span class="flex size-10 items-center justify-center rounded-lg bg-primary/15 text-primary">
                                    <x-icon :name="$feature['icon']" class="size-5" />
                                </span>
                                <h3 class="mt-4 font-semibold">{{ $feature['title'] }}</h3>
                                <p class="mt-2 text-sm text-muted-foreground">{{ $feature['text'] }}</p>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>

            {{-- How it works --}}
            <section id="how-it-works" class="scroll-mt-16 border-t border-border/60">
                <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6">
                    <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">Up and running in three steps</h2>

                    <ol class="mt-12 grid gap-8 md:grid-cols-3">
                        @foreach ($steps as $i => $step)
                            <li>
                                <span class="flex size-10 items-center justify-center rounded-full border-2 border-primary font-bold text-primary">{{ $i + 1 }}</span>
                                <h3 class="mt-4 font-semibold">{{ $step['title'] }}</h3>
                                <p class="mt-2 text-sm text-muted-foreground">{{ $step['text'] }}</p>
                            </li>
                        @endforeach
                    </ol>

                    <div class="mt-16 grid gap-4 sm:grid-cols-3">
                        <div class="flex items-start gap-3 rounded-xl border border-border p-5">
                            <x-icon name="shield-check" class="mt-0.5 size-5 text-primary" />
                            <p class="text-sm"><span class="font-semibold">Your data stays yours.</span> <span class="text-muted-foreground">Each gym's records are kept separate from other gyms.</span></p>
                        </div>
                        <div class="flex items-start gap-3 rounded-xl border border-border p-5">
                            <x-icon name="clock" class="mt-0.5 size-5 text-primary" />
                            <p class="text-sm"><span class="font-semibold">Philippine time.</span> <span class="text-muted-foreground">Expirations and attendance follow Manila time.</span></p>
                        </div>
                        <div class="flex items-start gap-3 rounded-xl border border-border p-5">
                            <x-icon name="smartphone" class="mt-0.5 size-5 text-primary" />
                            <p class="text-sm"><span class="font-semibold">Works on any device.</span> <span class="text-muted-foreground">Phone, tablet, or computer — nothing to install.</span></p>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Pricing --}}
            <section id="pricing" class="scroll-mt-16 border-t border-border/60 bg-card/40">
                <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6 short:py-8">
                    <div class="mx-auto max-w-2xl text-center">
                        <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">Pricing that grows with your gym</h2>
                        <p class="mt-4 text-muted-foreground">Start small, upgrade when you need more members, staff, or branches. Prices in Philippine pesos.</p>
                        <p class="mt-2 text-sm text-muted-foreground">{{ config('legal.tax_notice') ?: 'Draft pricing: VAT/tax wording awaits operator confirmation.' }}</p>
                    </div>

                    {{-- Monthly / yearly switch: CSS-only (radio :checked + group-has), no JavaScript --}}
                    <div class="group/pricing">
                        <fieldset class="mx-auto mt-10 flex w-fit rounded-full border border-border bg-background p-1">
                            <legend class="sr-only">Billing period</legend>
                            <label class="flex h-10 cursor-pointer items-center rounded-full px-5 text-sm font-medium text-muted-foreground has-checked:bg-primary has-checked:text-primary-foreground has-focus-visible:ring-2 has-focus-visible:ring-ring">
                                <input type="radio" name="billing" id="billing-monthly" value="monthly" class="sr-only" checked>
                                Monthly
                            </label>
                            <label class="flex h-10 cursor-pointer items-center gap-2 rounded-full px-5 text-sm font-medium text-muted-foreground has-checked:bg-primary has-checked:text-primary-foreground has-focus-visible:ring-2 has-focus-visible:ring-ring">
                                <input type="radio" name="billing" id="billing-yearly" value="yearly" class="sr-only">
                                Yearly
                                <span class="rounded-full bg-emerald-500/15 px-2 py-0.5 text-xs text-emerald-400">2 months free</span>
                            </label>
                        </fieldset>

                        <ul id="plans" class="mt-8 grid scroll-mt-20 gap-4 lg:grid-cols-3 roomy:gap-6 short:mt-4 short:grid-cols-3 short:gap-3">
                            @foreach ($plans as $key => $plan)
                                @php($popular = $plan['popular'] ?? false)
                                <li @class([
                                    'relative flex flex-col rounded-2xl bg-background p-5 roomy:p-8 short:p-3',
                                    'border-2 border-primary shadow-xl shadow-primary/10' => $popular,
                                    'border border-border' => ! $popular,
                                ])>
                                    @if ($popular)
                                        <span class="absolute -top-3 left-6 rounded-full bg-primary px-3 py-1 text-xs font-semibold text-primary-foreground">Most popular</span>
                                    @endif

                                    <h3 class="text-base font-semibold roomy:text-lg short:text-sm">{{ $plan['name'] }}</h3>
                                    <p class="mt-1 text-sm text-muted-foreground lg:min-h-10 short:hidden">{{ $plan['tagline'] }}</p>

                                    {{-- Monthly price (default) --}}
                                    <div class="mt-4 group-has-[#billing-yearly:checked]/pricing:hidden short:mt-1">
                                        <p class="flex items-baseline gap-1">
                                            <span class="text-3xl font-bold tracking-tight roomy:text-4xl short:text-2xl">{{ $peso($plan['monthly_price']) }}</span>
                                            <span class="text-muted-foreground">/ month</span>
                                        </p>
                                        @isset($plan['extra_branch_monthly_price'])
                                            <p class="mt-1 text-sm text-muted-foreground short:mt-0 short:text-xs">+{{ $peso($plan['extra_branch_monthly_price']) }}/month per extra branch</p>
                                        @endisset
                                    </div>

                                    {{-- Yearly price: pay 10 months, get 12 --}}
                                    <div class="mt-4 hidden group-has-[#billing-yearly:checked]/pricing:block short:mt-1">
                                        <p class="flex items-baseline gap-1">
                                            <span class="text-3xl font-bold tracking-tight roomy:text-4xl short:text-2xl">{{ $peso($plan['monthly_price'] * $monthsCharged) }}</span>
                                            <span class="text-muted-foreground">/ year</span>
                                        </p>
                                        <p class="mt-1 text-sm text-emerald-400 short:mt-0 short:text-xs">
                                            Save {{ $peso($plan['monthly_price'] * (12 - $monthsCharged)) }} — pay {{ $monthsCharged }} months, get 12
                                        </p>
                                        @isset($plan['extra_branch_monthly_price'])
                                            <p class="mt-1 text-sm text-muted-foreground short:mt-0 short:text-xs">+{{ $peso($plan['extra_branch_monthly_price'] * $monthsCharged) }}/year per extra branch</p>
                                        @endisset
                                    </div>

                                    {{-- Plan limits --}}
                                    <ul class="mt-4 space-y-1 rounded-lg bg-muted px-4 py-2.5 text-sm font-medium roomy:mt-6 short:mt-2 short:space-y-0 short:px-3 short:py-1.5 short:text-xs">
                                        @foreach ($plan['highlights'] as $highlight)
                                            <li>{{ $highlight }}</li>
                                        @endforeach
                                    </ul>

                                    {{-- Every feature on every card, grouped by category; what this plan lacks is grayed out --}}
                                    @php($planIndex = $loop->index)
                                    <div class="mt-4 flex-1 space-y-3 roomy:mt-6 roomy:space-y-5 short:mt-2 short:space-y-1.5">
                                        @foreach (config('plans.card_features') as $category => $rows)
                                            <div>
                                                <h4 class="mb-1.5 text-xs font-semibold tracking-wide text-muted-foreground uppercase short:mb-0.5 short:text-[11px]">{{ $category }}</h4>
                                                <ul class="space-y-1.5 text-sm leading-snug roomy:space-y-2.5 short:space-y-0 short:text-xs short:leading-4">
                                                    @foreach ($rows as $row)
                                                        @php($value = $row[$planIndex + 1])
                                                        @if ($value === false)
                                                            <li class="flex items-start gap-3 text-muted-foreground short:gap-2">
                                                                <x-icon name="x" class="mt-0.5 size-4 shrink-0 short:size-3" />
                                                                <span><span class="sr-only">Not included: </span>{{ $row[0] }}</span>
                                                            </li>
                                                        @else
                                                            <li class="flex items-start gap-3 short:gap-2">
                                                                <x-icon name="check" class="mt-0.5 size-4 shrink-0 text-primary short:size-3" />
                                                                {{ is_string($value) ? $value : $row[0] }}
                                                            </li>
                                                        @endif
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endforeach
                                    </div>

                                    <a href="{{ $requestAccess($key) }}" @class([
                                        'mt-6 inline-flex h-11 w-full items-center justify-center rounded-md font-medium short:mt-2 short:text-xs',
                                        'bg-primary text-primary-foreground hover:bg-primary/90' => $popular,
                                        'border border-border hover:bg-accent' => ! $popular,
                                    ])>Request {{ $plan['name'] }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="mt-8 text-center text-sm text-muted-foreground">
                        Not sure yet?
                        <form method="POST" action="{{ route('demo.store') }}" class="inline">
                            @csrf
                            <button type="submit" class="min-h-11 cursor-pointer font-medium text-foreground underline underline-offset-4">Try the demo first</button>
                        </form>
                    </div>

                    {{-- Full comparison (native <details>, no JavaScript) --}}
                    <details class="group mt-12 rounded-2xl border border-border bg-background">
                        <summary class="flex h-14 cursor-pointer list-none items-center justify-between gap-4 px-6 font-medium [&::-webkit-details-marker]:hidden">
                            Compare all features
                            <span aria-hidden="true" class="text-xl leading-none text-primary transition-transform group-open:rotate-45 motion-reduce:transition-none">+</span>
                        </summary>
                        <div class="overflow-x-auto border-t border-border">
                            <table class="w-full min-w-160 text-left text-sm">
                                <caption class="sr-only">Plan comparison</caption>
                                <thead>
                                    <tr class="border-b border-border">
                                        <th scope="col" class="px-6 py-3 font-medium text-muted-foreground">Feature</th>
                                        @foreach ($plans as $plan)
                                            <th scope="col" class="px-4 py-3 font-semibold">{{ $plan['name'] }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    @foreach ($comparison as [$feature, $starter, $pro, $multi])
                                        <tr>
                                            <th scope="row" class="px-6 py-3 font-normal">{{ $feature }}</th>
                                            @foreach ([$starter, $pro, $multi] as $value)
                                                <td class="px-4 py-3">
                                                    @if ($value === true)
                                                        <x-icon name="check" class="size-5 text-primary" /><span class="sr-only">Included</span>
                                                    @elseif ($value === false)
                                                        <span aria-hidden="true" class="text-muted-foreground">✕</span><span class="sr-only">Not included</span>
                                                    @elseif ($value === null)
                                                        <span aria-hidden="true" class="text-muted-foreground">—</span><span class="sr-only">Not applicable</span>
                                                    @else
                                                        {{ $value }}
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                </div>
            </section>

            {{-- FAQ (native <details>, no JavaScript) --}}
            <section id="faq" class="scroll-mt-16 border-t border-border/60">
                <div class="mx-auto max-w-3xl px-4 py-20 sm:px-6">
                    <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">Questions</h2>
                    <div class="mt-10 divide-y divide-border border-y border-border">
                        @foreach ($faqs as $faq)
                            <details class="group py-5">
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-medium [&::-webkit-details-marker]:hidden">
                                    {{ $faq['q'] }}
                                    <span aria-hidden="true" class="text-xl leading-none text-primary transition-transform group-open:rotate-45 motion-reduce:transition-none">+</span>
                                </summary>
                                <p class="mt-3 text-sm text-muted-foreground">{{ $faq['a'] }}</p>
                            </details>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- Final call to action --}}
            <section class="border-t border-border/60">
                <div class="mx-auto max-w-6xl px-4 py-20 text-center sm:px-6">
                    <h2 class="text-3xl font-bold tracking-tight text-balance sm:text-4xl">Ready to ditch the logbook?</h2>
                    <p class="mx-auto mt-4 max-w-xl text-muted-foreground">Request access in a minute — or try the demo first, no sign-up needed.</p>
                    <a href="{{ $requestAccess() }}" class="mt-8 inline-flex h-11 items-center gap-2 rounded-md bg-primary px-6 font-medium text-primary-foreground hover:bg-primary/90">
                        Request access
                        <x-icon name="arrow-right" class="size-4" />
                    </a>
                </div>
            </section>
        </main>

        @include('marketing.legal-footer')
    </body>
</html>
