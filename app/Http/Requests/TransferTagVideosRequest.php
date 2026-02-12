<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransferTagVideosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Admin users are authorized via middleware
    }

    public function rules(): array
    {
        return [
            'target_tag_id' => 'required|integer|exists:tags,id',
        ];
    }

    public function messages(): array
    {
        return [
            'target_tag_id.required' => 'The target tag ID is required.',
            'target_tag_id.integer' => 'The target tag ID must be an integer.',
            'target_tag_id.exists' => 'The selected target tag does not exist.',
        ];
    }
}
