<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class, 'USER_EMAIL'),
            ],
            'password' => $this->passwordRules(),
        ])->validate();

        return User::create([
            'USER_FULLNAME' => $input['name'],
            'USER_EMAIL' => $input['email'],
            'USER_PASSWORD' => Hash::make($input['password']),
            'USER_ROLE_ID' => 2, // Default to user role
        ]);
    }
}
