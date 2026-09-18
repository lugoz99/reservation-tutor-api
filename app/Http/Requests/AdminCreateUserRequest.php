<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminCreateUserRequest extends FormRequest
{
  public function authorize(): bool
  {
    return $this->user()?->role_id === 1;
  }

  public function rules(): array
  {
    return [
      'first_names' => ['required', 'string', 'max:100'],
      'last_names' => ['required', 'string', 'max:100'],
      'email' => ['required', 'email', 'max:255', 'unique:users,email'],
      'password' => ['required', 'string', 'min:8', 'confirmed'],
      'role_id' => ['required', 'integer', 'exists:roles,id', 'in:1,2,3'],
      'hourly_rate' => [
        'required_if:role_id,2',
        'nullable',
        'integer',
        'min:30000',
        'max:100000',
      ],
      'phone' => ['nullable', 'string', 'max:50'],
      'photo' => ['nullable', 'string', 'max:255'],
    ];
  }
}
