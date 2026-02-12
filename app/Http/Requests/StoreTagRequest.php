<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Admin users are authorized via middleware
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:tags,name',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The tag name is required.',
            'name.string' => 'The tag name must be a string.',
            'name.max' => 'The tag name may not be greater than 255 characters.',
            'name.unique' => 'A tag with this name already exists.',
        ];
    }
}
