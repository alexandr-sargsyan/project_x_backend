<?php

namespace App\Http\Requests;

use App\Enums\PacingEnum;
use App\Enums\PlatformEnum;
use App\Enums\ProductionLevelEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVideoReferenceRequest extends FormRequest
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
            // Display Fields
            'title' => ['required', 'string', 'max:255'],
            'source_url' => ['required', 'url'],
            'preview_url' => ['nullable', 'url'],
            'preview_embed' => ['nullable', 'string'],
            'public_summary' => ['nullable', 'string'],
            'details_public' => ['nullable', 'array'],
            'duration_sec' => ['nullable', 'integer', 'min:1'],

            // Filter Fields
            'category_id' => ['required', 'exists:categories,id'],
            'platform' => ['nullable', 'string', Rule::in(PlatformEnum::values())],
            'pacing' => ['nullable', 'string', Rule::in(PacingEnum::values())],
            'hook_type' => ['nullable', 'string'],
            'production_level' => ['nullable', 'string', Rule::in(ProductionLevelEnum::values())],
            'has_visual_effects' => ['nullable', 'boolean'],
            'has_3d' => ['nullable', 'boolean'],
            'has_animations' => ['nullable', 'boolean'],
            'has_typography' => ['nullable', 'boolean'],
            'has_sound_design' => ['nullable', 'boolean'],

            // Search Fields
            'search_profile' => ['required', 'string'],
            'search_metadata' => ['nullable', 'string'],

            // Tags
            'tags' => ['required', 'array'],
            'tags.*' => ['exists:tags,id'],

            // Tutorials
            'tutorials' => ['nullable', 'array'],
            'tutorials.*.tutorial_url' => ['nullable', 'url'],
            'tutorials.*.label' => ['nullable', 'string'],
            'tutorials.*.start_sec' => ['nullable', 'integer', 'min:0'],
            'tutorials.*.end_sec' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * Валидация tutorials: хотя бы одно из полей должно быть заполнено
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->has('tutorials')) {
                foreach ($this->input('tutorials', []) as $index => $tutorial) {
                    $hasUrl = !empty($tutorial['tutorial_url'] ?? null);
                    $hasSegment = !empty($tutorial['label'] ?? null) 
                        && !empty($tutorial['start_sec'] ?? null) 
                        && !empty($tutorial['end_sec'] ?? null);

                    if (!$hasUrl && !$hasSegment) {
                        $validator->errors()->add(
                            "tutorials.{$index}",
                            'Хотя бы одно из полей должно быть заполнено: tutorial_url ИЛИ (label + start_sec + end_sec)'
                        );
                    }
                }
            }
        });
    }
}
