<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTutorAvailabilityRequest extends FormRequest
{
  public function authorize(): bool
  {
    return $this->user()?->role_id === 2;
  }

  public function rules(): array
  {
    return [
      'name' => ['required', 'string', 'max:150'],
      'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
      'hours' => ['required', 'array', 'min:1', 'max:10'],
      'hours.*' => [
        'required',
        'distinct',
        'date_format:H:i',
        Rule::in($this->allowedHours()),
      ],
    ];
  }

  private function allowedHours(): array
  {
    return array_map(
      static fn(int $hour): string => sprintf('%02d:00', $hour),
      range(8, 17)
    );
  }
}
