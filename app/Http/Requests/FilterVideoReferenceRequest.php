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
            'category_id' => ['nullable'],
            'category_id.*' => ['integer', 'exists:categories,id'],
            'platform' => ['nullable', 'string', Rule::in(PlatformEnum::values())],
            'pacing' => ['nullable', 'string', Rule::in(PacingEnum::values())],
            'hook_type' => ['nullable', 'string'],
            'production_level' => ['nullable', 'string', Rule::in(ProductionLevelEnum::values())],
            'has_visual_effects' => ['nullable', 'boolean'],
            'has_3d' => ['nullable', 'boolean'],
            'has_animations' => ['nullable', 'boolean'],
            'has_typography' => ['nullable', 'boolean'],
            'has_sound_design' => ['nullable', 'boolean'],
            'has_tutorial' => ['nullable', 'boolean'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
            'sort_by' => ['nullable', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
