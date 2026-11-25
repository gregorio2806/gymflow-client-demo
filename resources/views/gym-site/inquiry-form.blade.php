<div class="max-w-2xl space-y-4 rounded-xl border bg-card p-6">
    <h3 class="text-xl font-semibold">Send an inquiry</h3>
    <p class="text-sm text-muted-foreground">Ask about plans, hours, or visiting the gym. Please leave out medical information, ID numbers, and card details.</p>
    @if($preview)
        <p class="text-sm">Preview only. Visitors can send inquiries after you publish this draft.</p>
    @else
        @if(session('inquiry_sent') === app(\App\Tenancy\CurrentGym::class)->id())<p role="status" class="rounded-md border p-4">Your inquiry has been received. The gym can respond using your email.</p>@endif
        @if($inquiryFeedback && $errors->any())<div role="alert" class="space-y-2 text-sm text-destructive"><p>Please check the form.</p><ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @endif
    <form action="{{ $preview ? '#' : route('gym-site.inquiries.store', ['gym' => $slug]) }}" method="post" class="space-y-5">
        @csrf
        <input type="hidden" name="form_token" value="{{ $formToken }}">
        <fieldset @disabled($preview) class="space-y-5">
            <div class="grid gap-2"><label for="inquiry-name" class="text-sm font-medium">Name</label><input id="inquiry-name" name="name" autocomplete="name" required maxlength="100" value="{{ !$inquiryFeedback ? '' : old('name') }}" class="h-11 w-full rounded-md border bg-background px-3 text-base focus-visible:outline-2 focus-visible:outline-ring"></div>
            <div class="grid gap-2"><label for="inquiry-email" class="text-sm font-medium">Email</label><input id="inquiry-email" name="email" type="email" autocomplete="email" required maxlength="255" value="{{ !$inquiryFeedback ? '' : old('email') }}" class="h-11 w-full rounded-md border bg-background px-3 text-base focus-visible:outline-2 focus-visible:outline-ring"></div>
            <div class="grid gap-2"><label for="inquiry-message" class="text-sm font-medium">Message</label><textarea id="inquiry-message" name="message" required maxlength="2000" rows="5" class="w-full rounded-md border bg-background px-3 py-3 text-base focus-visible:outline-2 focus-visible:outline-ring">{{ !$inquiryFeedback ? '' : old('message') }}</textarea><p class="text-sm text-muted-foreground">Up to 2,000 characters.</p></div>
            <div hidden aria-hidden="true"><label for="inquiry-website">Leave blank</label><input id="inquiry-website" name="website" tabindex="-1" autocomplete="off"></div>
            <label for="inquiry-consent" class="flex min-h-11 items-start gap-3 py-3 text-sm leading-6"><input id="inquiry-consent" name="consent" type="checkbox" value="1" required class="mt-1 size-5 shrink-0 accent-primary"><span>I have read the <a href="#privacy" class="underline" data-open-privacy>privacy notice</a> and consent to {{ $gymName }} using my name, email, and message to respond to this inquiry.</span></label>
            <button type="submit" class="inline-flex min-h-11 items-center rounded-md bg-primary px-5 font-medium text-primary-foreground focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ring disabled:opacity-50">Send inquiry</button>
        </fieldset>
    </form>
</div>
