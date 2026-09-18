<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'cancellation_reason' => [
                'required',
                'string',
                'min:10',
                'max:500',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'cancellation_reason.required' => 'El motivo de cancelación es obligatorio.',
            'cancellation_reason.string' => 'El motivo de cancelación debe ser texto.',
            'cancellation_reason.min' => 'El motivo debe tener al menos 10 caracteres.',
            'cancellation_reason.max' => 'El motivo no puede superar los 500 caracteres.',
        ];
    }
}
