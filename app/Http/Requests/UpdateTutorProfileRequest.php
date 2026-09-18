<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTutorProfileRequest extends FormRequest
{
  public function authorize(): bool
  {
    return $this->user()?->role_id === 2;
  }

  public function rules(): array
  {
    return [
      'first_names' => ['sometimes', 'required', 'string', 'max:100'],
      'last_names' => ['sometimes', 'required', 'string', 'max:100'],
      'email' => [
        'sometimes',
        'required',
        'email',
        'max:255',
        Rule::unique('users', 'email')->ignore($this->user()->id),
      ],
      'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
      'photo' => ['sometimes', 'nullable', 'string', 'max:255'],
      'hourly_rate' => ['sometimes', 'required', 'integer', 'min:30000', 'max:100000'],
    ];
  }
}
