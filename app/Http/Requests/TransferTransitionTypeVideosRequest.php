<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransferTransitionTypeVideosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Admin users are authorized via middleware
    }

    public function rules(): array
    {
        return [
            'target_transition_type_id' => 'required|integer|exists:transition_types,id',
        ];
    }

    public function messages(): array
    {
        return [
            'target_transition_type_id.required' => 'The target transition type ID is required.',
            'target_transition_type_id.integer' => 'The target transition type ID must be an integer.',
            'target_transition_type_id.exists' => 'The selected target transition type does not exist.',
        ];
    }
}
