<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        $role = Role::firstOrCreate(['name' => 'Asistente']);

        return User::create([
            'first_name' => $input['first_name'],
            'last_name' => $input['last_name'] ?? '',
            'dni' => 'CNV-'.strtoupper(Str::random(7)),
            'email' => $input['email'],
            'affiliation' => $input['affiliation'],
            'country' => $input['country'],
            'state' => $input['state'],
            'password' => $input['password'],
            'role_id' => $role->id,
        ]);
    }
}
