<details id="privacy" class="max-w-3xl scroll-mt-24 rounded-xl border bg-card p-6">
    <summary class="min-h-11 cursor-pointer text-lg font-semibold">Privacy notice</summary>
    <div class="mt-4 space-y-5 text-sm leading-6">
        <p>{{ $gymName }} manages the personal information collected for its gym services. This notice describes the information entered into this gym management service and website. Version {{ \App\Support\WebsitePrivacy::VERSION }}.</p>
        <div><h3 class="font-semibold">Information and purpose</h3><p>Member records may include name, mobile number, optional email, membership plan and dates, attendance, recorded offline payments, and member login details. These support membership administration, check-in, account access, and payment record keeping. Contact inquiries contain your name, email, and message so the gym can respond to your inquiry. Do not send medical information, government ID numbers, or card details through this form.</p></div>
        <div><h3 class="font-semibold">Your choices</h3><p>Sending an inquiry is optional. The form requests your consent to use its information to answer that inquiry; it does not subscribe you to marketing. You may instead contact the gym directly. The gym will explain the applicable basis and any consent needed for its membership services.</p></div>
        <div><h3 class="font-semibold">Access, storage, and retention</h3><p>The gym owner can read website inquiries. Authorized gym personnel access member records according to their role; the gym management service processes those records for the gym. Website inquiries are removed from the active service after {{ \App\Support\WebsitePrivacy::RETENTION_DAYS }} days by the daily retention job. The gym's additional information below explains its providers, hosting location, backups, and member/accounting record retention.</p></div>
        @if(!empty($privacy['details']))<div><h3 class="font-semibold">Gym-specific information</h3><p class="break-words whitespace-pre-line">{{ $privacy['details'] }}</p></div>@endif
        <div><h3 class="font-semibold">Your privacy rights</h3><p>You may ask the gym for access or correction, object to processing, withdraw consent, or request erasure/blocking or a portable copy, as applicable. The gym will review requests and explain any required retention. You may raise a concern with the National Privacy Commission.</p></div>
        <div><h3 class="font-semibold">Contact the gym</h3>
            @if(!empty($privacy['contact_name']))<p class="break-words">{{ $privacy['contact_name'] }}</p>@endif
            @if(filter_var($privacy['contact_email'] ?? '', FILTER_VALIDATE_EMAIL))<a class="inline-flex min-h-11 break-all items-center underline" href="mailto:{{ $privacy['contact_email'] }}">{{ $privacy['contact_email'] }}</a>@else<p>Ask the gym front desk about its privacy contact and record retention.</p>@endif
        </div>
        <div><h3 class="font-semibold">Website cookies</h3><p>Necessary session and security cookies protect form submissions and member sign-in. This website does not add analytics or advertising cookies.</p></div>
    </div>
</details>
