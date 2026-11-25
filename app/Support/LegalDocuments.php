<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LegalDocuments
{
    public static function ready(): bool
    {
        return config('legal.published') && collect(['business_name', 'business_address', 'contact_email', 'privacy_email', 'tax_notice', 'providers', 'retention', 'cancellation', 'refunds'])->every(fn ($key) => filled(config('legal.'.$key)));
    }

    public static function documents(): array
    {
        $name = config('legal.business_name') ?: config('app.name');
        $contact = config('legal.contact_email') ?: 'Contact details pending operator review.';
        $privacy = config('legal.privacy_email') ?: 'Privacy contact pending operator review.';

        return [
            ['slug' => 'privacy', 'title' => 'Privacy policy', 'sections' => [
                ['title' => 'Who handles your information', 'text' => "$name operates this service. Each gym controls its member records and decides why they are used; the service processes those records on the gym’s instructions. The operator controls platform account and access-request data. Privacy contact: $privacy"],
                ['title' => 'Information and purposes', 'text' => 'Owner and team accounts use name, email, password hash, role, branch, account agreement and security records for access and administration. Gym records include member name, mobile, optional email, membership periods, attendance, offline payments, workouts and schedules. Public inquiries include contact details, messages and acknowledgment records. Optional calorie inputs are stored encrypted when a member chooses Save and estimate; they are not required for membership or account terms. QR credentials and membership check codes are stored as hashes. We do not store card details.'],
                ['title' => 'Grounds and choices', 'text' => 'Account administration relies on the service relationship and applicable legal obligations. Gyms must identify appropriate lawful grounds for member processing and provide their own notice, including guardian involvement where needed. Agreeing to account terms acknowledges this notice; it does not authorize marketing or all optional health processing. Members control the calorie helper and can delete its saved inputs.'],
                ['title' => 'Recipients and storage', 'text' => config('legal.providers') ?: 'Draft: the operator must list the actual hosting, email, backup providers and processing countries before launch. Staff access follows their gym role and branch. No advertising or analytics service is connected by this feature.'],
                ['title' => 'Retention and protection', 'text' => config('legal.retention') ?: 'Draft: the operator must publish account, member, accounting, security-log and backup retention periods before launch. Website inquiries are pruned after 365 days and temporary report exports after 24 hours. Access checks, tenant isolation, hashed credentials and encrypted sensitive inputs protect records; no system is risk-free.'],
                ['title' => 'Your rights and requests', 'text' => "Ask your gym to access, correct, object to processing of, or request deletion/portability of member records. Contact $privacy for platform records or unresolved privacy questions. Requests are reviewed with identity checks and any applicable accounting/legal obligations. You may raise a complaint with the National Privacy Commission at privacy.gov.ph. Deletion from active storage does not immediately erase retained backups."],
            ]],
            ['slug' => 'terms', 'title' => 'Terms of service and data processing agreement', 'sections' => [
                ['title' => 'Using the service', 'text' => 'Accounts are invitation-only or issued by a gym. Protect login details, use only your authorized gym records, and contact your gym about member access. Owners are responsible for accurate gym content, lawful member processing and team permissions. Fitness estimates are informational and are not medical advice.'],
                ['title' => 'Plans and payment records', 'text' => 'Platform plan prices are displayed in Philippine pesos. The annual option is priced at ten monthly payments for twelve months. Confirm the offered plan, tax wording and service period before purchasing. This release records offline gym payments; recording or voiding a payment does not transfer money or issue a refund. No online payment gateway is connected.'],
                ['title' => 'Data processing agreement — gym instructions', 'text' => 'The gym is the controller of member data and instructs the operator to process it only to provide the selected gym features and authorized administration. The gym must provide a lawful basis, give members its privacy notice, keep records accurate, and obtain any specific optional health/guardian consent required. General account agreement is separate from optional health-data consent.'],
                ['title' => 'Data processing agreement — operator obligations', 'text' => 'The operator limits access to authorized people, maintains reasonable security and confidentiality, uses the providers disclosed in the privacy policy, and assists the gym with verified rights requests and incident investigation. The operator will notify the affected gym without undue delay of a confirmed personal-data incident so the parties can assess applicable notification duties. Export, return, correction and erasure requests are arranged with the operator; there is no automatic account-cancellation or full-data-export service in this release.'],
                ['title' => 'Acceptable use and changes', 'text' => 'Do not upload illegal or unlicensed content, collect unnecessary sensitive information, share QR credentials publicly, probe other gyms, or interfere with the service. Feature access can be restricted to protect records and investigate misuse. Material policy changes receive a new version; members review revised terms before returning to their private portal. Nothing in these terms removes rights that applicable Philippine law makes mandatory.'],
                ['title' => 'Cancellation and questions', 'text' => (config('legal.cancellation') ?: 'Draft: cancellation, access periods and data-return windows await operator review. Do not assume automatic renewal, pro-rata refunds or service credits.')." Contact: $contact"],
            ]],
            ['slug' => 'cookies', 'title' => 'Cookie policy', 'sections' => [
                ['title' => 'Necessary cookies', 'text' => 'Session cookies keep you signed in and separate portal verification from staff access. The CSRF cookie protects form submissions. Appearance and sidebar cookies remember interface preferences. Clearing or blocking necessary cookies may prevent sign-in and form submission.'],
                ['title' => 'Optional tracking', 'text' => 'This release uses no advertising or analytics cookies. An optional tracker would require a separate notice and appropriate choice before activation. Account agreement does not opt you into tracking. You can clear cookies in your browser settings.'],
            ]],
            ['slug' => 'refund', 'title' => 'Refund and cancellation policy', 'sections' => [
                ['title' => 'Platform subscriptions', 'text' => config('legal.cancellation') ?: 'Draft: the operator must confirm monthly/annual cancellation rules, remaining access, renewal arrangements and data-return windows before accepting paid subscriptions.'],
                ['title' => 'Refund requests', 'text' => config('legal.refunds') ?: 'Draft: refund eligibility, annual-plan treatment, outages and response/payment timelines await operator review. Mandatory consumer rights remain applicable; this page does not impose a no-refund rule.'],
                ['title' => 'Gym membership payments', 'text' => "A gym sets its own membership cancellation/refund rules. Ask that gym about its offline Cash, GCash, Maya or bank-transfer payments. Platform records and voids do not move money. Platform questions: $contact"],
            ]],
            ['slug' => 'contact', 'title' => 'Business and contact details', 'sections' => [
                ['title' => 'Operator', 'text' => "$name\n".(config('legal.business_address') ?: 'Business address pending operator review.')],
                ['title' => 'Contact', 'text' => (config('legal.contact_name') ?: '')."\n$contact\nPrivacy: $privacy\n".(config('legal.phone') ?: '')."\nFacebook: ".(config('legal.facebook_name') ?: 'Not supplied.')],
                ['title' => 'Pricing and taxes', 'text' => config('legal.tax_notice') ?: 'Draft: VAT/tax treatment must be confirmed by the operator before selling subscriptions. All displayed plan prices use Philippine pesos.'],
            ]],
        ];
    }

    public static function bundle(): array
    {
        $documents = self::documents();

        return ['version' => hash('sha256', json_encode([config('legal.version'), self::ready(), $documents], JSON_THROW_ON_ERROR)), 'label' => config('legal.version'), 'draft' => ! self::ready(), 'documents' => $documents];
    }

    public static function validate(array $input): array
    {
        if (app()->isProduction() && ! self::ready()) {
            throw ValidationException::withMessages(['terms_accepted' => 'Account policies are awaiting publication. Contact the operator.']);
        }

        return Validator::make($input, ['terms_accepted' => ['required', 'accepted'], 'policy_version' => ['required', 'string', Rule::in([self::bundle()['version']])]], ['terms_accepted.required' => 'Agree to the account terms and acknowledge the privacy policy.', 'terms_accepted.accepted' => 'Agree to the account terms and acknowledge the privacy policy.', 'policy_version.in' => 'Policies changed. Refresh and review them before agreeing.'])->validate();
    }

    public static function acceptUser(User $user, array $input): void
    {
        self::validate($input);
        if ($user->terms_version !== self::bundle()['version'] || ! $user->terms_accepted_at) {
            $user->forceFill(['terms_version' => self::bundle()['version'], 'terms_accepted_at' => now()])->save();
        }
    }
}
