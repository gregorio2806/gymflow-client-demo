<footer class="border-t border-border">
    <div class="mx-auto max-w-6xl space-y-4 px-4 py-8 text-sm">
        <p>{{ config('legal.business_name') ?: config('app.name') }} · {{ config('legal.business_address') ?: 'Business details pending review' }}</p>
        @if (config('legal.contact_email'))<p class="break-words">Contact: {{ config('legal.contact_email') }} · {{ config('legal.phone') }}</p>@endif
        <nav aria-label="Policies" class="flex flex-wrap gap-x-6 gap-y-2">
            @foreach (['privacy' => 'Privacy', 'terms' => 'Terms & DPA', 'cookies' => 'Cookies', 'refund' => 'Refunds', 'contact' => 'Contact'] as $slug => $label)
                <a href="{{ route('legal.show', ['document' => $slug]) }}" class="inline-flex min-h-11 items-center hover:underline">{{ $label }}</a>
            @endforeach
        </nav>
    </div>
</footer>
