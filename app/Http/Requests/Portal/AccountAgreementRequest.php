<?php

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;

class AccountAgreementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->attributes->get('portalMember') !== null;
    }

    public function rules(): array
    {
        return ['terms_accepted' => ['required', 'accepted'], 'policy_version' => ['required', 'string', 'size:64']];
    }
}
