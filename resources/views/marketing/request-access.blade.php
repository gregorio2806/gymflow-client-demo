@php
    $appName = config('app.name');
    $accessEmail = config('platform.access_email');
    $sent = session('status') === 'sent';

    $field = 'h-11 w-full rounded-md border border-input bg-input/30 px-3 text-base outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive';
@endphp
<!DOCTYPE html>
<html lang="en" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Request access — {{ $appName }}</title>
        <meta name="description" content="Ask for access to {{ $appName }}, gym management software for Philippine gyms.">
        <meta name="theme-color" content="#0a0a0a">
        <link rel="icon" href="/favicon.ico?v=clean-logo-2" sizes="any">
        <link rel="icon" href="/favicon.svg?v=clean-logo-2" type="image/svg+xml">
        @fonts
        @vite('resources/css/app.css')
    </head>
    <body class="bg-background font-sans text-foreground antialiased">
        <header class="border-b border-border/60">
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6">
                <a href="{{ route('home') }}" class="flex items-center gap-2 font-semibold tracking-tight">
                    <span class="flex size-8 items-center justify-center rounded-md bg-primary text-primary-foreground">
                        <x-icon name="dumbbell" class="size-5" />
                    </span>
                    <span class="whitespace-nowrap">{{ $appName }}</span>
                </a>
                <a href="{{ route('home') }}" class="inline-flex h-11 items-center rounded-md px-3 text-sm font-medium hover:bg-accent">Back to home</a>
            </div>
        </header>

        <main class="mx-auto max-w-xl px-4 py-12 sm:px-6">
            @if ($sent)
                <div role="status" class="rounded-2xl border border-border p-8 text-center">
                    <span class="mx-auto flex size-12 items-center justify-center rounded-full bg-primary/15 text-primary">
                        <x-icon name="check" class="size-6" />
                    </span>
                    <h1 class="mt-4 text-2xl font-semibold">Request sent</h1>
                    <p class="mt-2 text-muted-foreground">
                        Thanks! We'll review it and email you a link to set up your gym. This usually takes a day or two.
                    </p>
                    <a href="{{ route('home') }}" class="mt-6 inline-flex h-11 items-center rounded-md bg-primary px-6 font-medium text-primary-foreground hover:bg-primary/90">Back to home</a>
                </div>
            @else
                <h1 class="text-2xl font-semibold">Request access</h1>
                <p class="mt-2 text-muted-foreground">
                    Tell us about your gym. We'll email you a link to set up your account.
                    Prefer email? Write to <a href="mailto:{{ $accessEmail }}" class="font-medium text-foreground underline underline-offset-4">{{ $accessEmail }}</a>.
                </p>

                <form method="POST" action="{{ route('access-requests.store') }}" class="mt-8 grid gap-5" novalidate>
                    @csrf
                    <input type="hidden" name="form_started" value="{{ $formStarted }}">

                    {{-- Bot trap: hidden from people and screen readers --}}
                    <div class="hidden" aria-hidden="true">
                        <label for="website">Leave this empty</label>
                        <input id="website" type="text" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    @foreach ([
                        ['name', 'Your name', 'text', 'name', true],
                        ['email', 'Email', 'email', 'email', true],
                        ['phone', 'Mobile number (optional)', 'tel', 'tel', false],
                        ['gym_name', 'Gym name', 'text', 'organization', true],
                        ['location', 'Location (optional)', 'text', 'address-level2', false],
                    ] as [$name, $label, $type, $autocomplete, $required])
                        <div class="grid gap-2">
                            <label for="{{ $name }}" class="text-sm font-medium">{{ $label }}</label>
                            <input
                                id="{{ $name }}"
                                name="{{ $name }}"
                                type="{{ $type }}"
                                value="{{ old($name) }}"
                                autocomplete="{{ $autocomplete }}"
                                @if ($required) required @endif
                                @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
                                class="{{ $field }}"
                            >
                            @error($name)
                                <p id="{{ $name }}-error" class="text-sm text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    @endforeach

                    <div class="grid gap-2">
                        <label for="plan" class="text-sm font-medium">Plan</label>
                        <select id="plan" name="plan" required class="{{ $field }}">
                            @foreach ($plans as $plan)
                                <option value="{{ $plan->value }}" @selected(old('plan', $selectedPlan->value) === $plan->value)>
                                    {{ $plan->label() }} — ₱{{ number_format($plan->monthlyPrice()) }}/month
                                </option>
                            @endforeach
                        </select>
                        @error('plan')
                            <p class="text-sm text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid gap-2">
                        <label for="message" class="text-sm font-medium">Anything else? (optional)</label>
                        <textarea id="message" name="message" rows="3" maxlength="2000" class="w-full rounded-md border border-input bg-input/30 px-3 py-2 text-base outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50">{{ old('message') }}</textarea>
                    </div>

                    <label class="flex min-h-11 items-start gap-3 text-sm">
                        <input type="checkbox" name="consent" value="1" required class="mt-0.5 size-5 shrink-0 accent-primary" @checked(old('consent'))>
                        <span>
                            I agree that {{ $appName }} may use these details to contact me about access, as described in the <a class="underline" href="{{ route('legal.show', ['document' => 'privacy']) }}">privacy policy</a>. This does not subscribe me to marketing.
                        </span>
                    </label>
                    @error('consent')
                        <p class="-mt-3 text-sm text-red-400">Please tick the box so we can contact you.</p>
                    @enderror

                    <button type="submit" class="inline-flex h-11 cursor-pointer items-center justify-center rounded-md bg-primary px-6 font-medium text-primary-foreground hover:bg-primary/90">
                        Send request
                    </button>
                </form>
            @endif
        </main>
        @include('marketing.legal-footer')
    </body>
</html>
