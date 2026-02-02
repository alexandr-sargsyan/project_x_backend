<?php

namespace App\Http\Requests;

use App\Enums\PacingEnum;
use App\Enums\PlatformEnum;
use App\Enums\ProductionLevelEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterVideoReferenceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // На этапе MVP без авторизации
    }

    /**
     * Prepare the data for validation.
     * Преобразует строки "true"/"false" в булевы значения для boolean полей
     */
    protected function prepareForValidation(): void
    {
        $booleanFields = [
            'has_visual_effects',
            'has_3d',
            'has_animations',
            'has_typography',
            'has_sound_design',
            'has_ai',
            'has_tutorial',
        ];

        foreach ($booleanFields as $field) {
            if ($this->has($field)) {
                $value = $this->input($field);
                
                // Преобразуем строки "true"/"false" в булевы значения
                if (is_string($value)) {
                    $value = strtolower($value);
                    if ($value === 'true' || $value === '1') {
                        $this->merge([$field => true]);
                    } elseif ($value === 'false' || $value === '0' || $value === '') {
                        $this->merge([$field => false]);
                    }
                }
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string'],
            'id' => ['nullable', 'integer', 'min:1'],
            'source_url' => ['nullable', 'string', 'max:2048'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'platform' => ['nullable'],
            'platform.*' => ['string', Rule::in(PlatformEnum::values())],
            'pacing' => ['nullable'],
            'pacing.*' => ['string', Rule::in(PacingEnum::values())],
            'hook_ids' => ['nullable', 'array'],
            'hook_ids.*' => ['integer', 'exists:hooks,id'],
            'production_level' => ['nullable'],
            'production_level.*' => ['string', Rule::in(ProductionLevelEnum::values())],
            'has_visual_effects' => ['nullable', 'boolean'],
            'has_3d' => ['nullable', 'boolean'],
            'has_animations' => ['nullable', 'boolean'],
            'has_typography' => ['nullable', 'boolean'],
            'has_sound_design' => ['nullable', 'boolean'],
            'has_ai' => ['nullable', 'boolean'],
            'has_tutorial' => ['nullable', 'boolean'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
            'transition_type_ids' => ['nullable', 'array'],
            'transition_type_ids.*' => ['integer', 'exists:transition_types,id'],
            'sort_by' => ['nullable', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
