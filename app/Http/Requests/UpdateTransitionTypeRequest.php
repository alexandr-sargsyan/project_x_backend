<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTransitionTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Admin users are authorized via middleware
    }

    public function rules(): array
    {
        $transitionTypeId = $this->route('id');
        
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('transition_types', 'name')->ignore($transitionTypeId),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The transition type name is required.',
            'name.string' => 'The transition type name must be a string.',
            'name.max' => 'The transition type name may not be greater than 255 characters.',
            'name.unique' => 'A transition type with this name already exists.',
        ];
    }
}
