<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ChangePasswordRequest extends FormRequest
{
    /**
     * Only authenticated students can change their password
     * through the student endpoint.
     */
    public function authorize(): bool
    {
        return (int) $this->user()?->role_id === 3;
    }

    public function rules(): array
    {
        return [
            /**
             * Laravel verifies that this password matches
             * the password currently stored for the authenticated user.
             */
            'current_password' => [
                'required',
                'current_password',
            ],

            /**
             * The new password:
             * - is required
             * - must have the confirmation field
             * - must satisfy Laravel's default password rules
             */
            'new_password' => [
                'required',
                'confirmed',
                Password::defaults(),
            ],
        ];
    }
}