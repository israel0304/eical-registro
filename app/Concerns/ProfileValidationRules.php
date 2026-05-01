<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Validation\Rule;

trait ProfileValidationRules
{
    /**
     * Get the validation rules used to validate user profiles.
     *
     * @return array<string, array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>>
     */
    protected function profileRules(?int $userId = null): array
    {
        return [
            'first_name' => $this->firstNameRules(),
            'last_name' => $this->lastNameRules(),
            'dni' => $this->dniRules($userId),
            'email' => $this->emailRules($userId),
        ];
    }

    protected function firstNameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    protected function lastNameRules(): array
    {
        return ['nullable', 'string', 'max:255'];
    }

    protected function dniRules(?int $userId = null): array
    {
        return ['nullable', 'string', 'max:20'];
    }

    /**
     * Get the validation rules used to validate user emails.
     *
     * @return array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>
     */
    protected function emailRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'email',
            'max:255',
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ];
    }
}
