<?php

namespace App\Actions\Fortify;

use App\Models\LedgerInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        $isFirstUser = User::query()->doesntExist();

        if (! self::isOpenFor($input['email'] ?? null)) {
            throw ValidationException::withMessages([
                'email' => __('Registration is closed on this instance.'),
            ]);
        }

        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => $this->passwordRules(),
        ])->validate();

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
            'is_admin' => $isFirstUser,
        ]);
    }

    /**
     * Sign-up is always open for the very first user (the owner of the instance) and for
     * people with a pending invitation; anyone else only when the instance allows it.
     */
    public static function isOpenFor(?string $email): bool
    {
        if (config('ledgerfly.registration_enabled') || User::query()->doesntExist()) {
            return true;
        }

        return $email !== null && LedgerInvitation::query()
            ->where('email', Str::lower(trim($email)))
            ->where('expires_at', '>', now())
            ->exists();
    }
}
