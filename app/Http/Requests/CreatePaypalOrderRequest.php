<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePaypalOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role_id === 3;
    }

    public function rules(): array
    {
        return [
            'tutor_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],

            'date' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:today',
            ],

            'hours' => [
                'required',
                'array',
                'min:1',
            ],

            'hours.*' => [
                'required',
                'date_format:H:i',
            ],
        ];
    }
}
