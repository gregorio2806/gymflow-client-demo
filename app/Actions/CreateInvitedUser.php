<?php

namespace App\Actions;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Support\LegalDocuments;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Public sign-up is closed: accounts are only created from an emailed invitation
 * (staff invite or gym-owner invite). The link was sent to this address, so the
 * new account starts with a verified email.
 */
class CreateInvitedUser
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * @param  array<string, mixed>  $input  name, password, password_confirmation
     *
     * @throws ValidationException
     */
    public function handle(string $email, array $input): User
    {
        LegalDocuments::validate($input);
        Validator::make($input, [
            'name' => $this->nameRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        if (User::where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => __('An account with this email already exists. Log in instead.')]);
        }

        $user = new User;
        $user->forceFill([
            'name' => $input['name'],
            'email' => $email,
            'password' => $input['password'],
            'email_verified_at' => now(),
            'terms_version' => LegalDocuments::bundle()['version'],
            'terms_accepted_at' => now(),
        ])->save();

        return $user;
    }
}
