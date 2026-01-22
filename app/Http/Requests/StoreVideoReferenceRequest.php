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
            'preview_embed' => ['nullable', 'string'],
            'public_summary' => ['nullable', 'string'],
            'details_public' => ['nullable', 'array'],
            'duration_sec' => ['nullable', 'integer', 'min:1'],

            // Filter Fields
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => ['required', 'integer', 'exists:categories,id'],
            'platform' => ['nullable', 'string', Rule::in(PlatformEnum::values())],
            'pacing' => ['nullable', 'string', Rule::in(PacingEnum::values())],
            'hook_id' => ['nullable', 'integer', 'exists:hooks,id'],
            'production_level' => ['nullable', 'string', Rule::in(ProductionLevelEnum::values())],
            'has_visual_effects' => ['nullable', 'boolean'],
            'has_3d' => ['nullable', 'boolean'],
            'has_animations' => ['nullable', 'boolean'],
            'has_typography' => ['nullable', 'boolean'],
            'has_sound_design' => ['nullable', 'boolean'],

            // Rating
            'rating' => ['nullable', 'integer', 'min:0', 'max:10'],

            // Search Fields
            'search_profile' => ['nullable', 'string'],

            // Tags (массив имен тегов, необязательное поле)
            'tags' => ['nullable', 'array'],
            'tags.*' => ['required', 'string', 'max:255'],

            // Tutorials
            'tutorials' => ['nullable', 'array'],
            'tutorials.*' => ['required', 'array'],
            // Mode: 'new' или 'select'
            'tutorials.*.mode' => ['required', 'string', Rule::in(['new', 'select'])],
            // Для mode='select': tutorial_id обязателен
            'tutorials.*.tutorial_id' => ['nullable', 'integer', 'exists:tutorials,id'],
            // Для mode='new': tutorial_url и label обязательны
            'tutorials.*.tutorial_url' => ['nullable', 'url', 'max:2048'],
            'tutorials.*.label' => ['nullable', 'string', 'max:255'],
            'tutorials.*.start_sec' => ['nullable', 'integer', 'min:0'],
            'tutorials.*.end_sec' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * Валидация tutorials: либо tutorial_id, либо tutorial_url/label должны быть заполнены
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $tutorials = $this->input('tutorials');
            
            // Пропускаем валидацию, если tutorials не передан или null
            if ($tutorials === null || !is_array($tutorials)) {
                return;
            }

            // Валидируем каждый tutorial
            foreach ($tutorials as $index => $tutorial) {
                // Проверяем, что tutorial - это массив
                if (!is_array($tutorial)) {
                    $validator->errors()->add(
                        "tutorials.{$index}",
                        'Tutorial must be an array'
                    );
                    continue;
                }

                $mode = $tutorial['mode'] ?? 'new';
                $hasTutorialId = !empty($tutorial['tutorial_id'] ?? null);
                $hasUrl = !empty($tutorial['tutorial_url'] ?? null);
                $hasLabel = !empty($tutorial['label'] ?? null);

                if ($mode === 'select') {
                    // В режиме select tutorial_id обязателен
                    if (!$hasTutorialId) {
                        $validator->errors()->add(
                            "tutorials.{$index}.tutorial_id",
                            'Tutorial ID is required when mode is "select"'
                        );
                    }
                } else {
                    // В режиме new tutorial_url и label обязательны
                    if (!$hasUrl) {
                        $validator->errors()->add(
                            "tutorials.{$index}.tutorial_url",
                            'Tutorial URL is required when mode is "new"'
                        );
                    }
                    if (!$hasLabel) {
                        $validator->errors()->add(
                            "tutorials.{$index}.label",
                            'Label is required when mode is "new"'
                        );
                    }
                }

                // Дополнительная валидация: если указан end_sec, он должен быть больше start_sec
                if (isset($tutorial['start_sec']) && isset($tutorial['end_sec']) 
                    && $tutorial['start_sec'] !== null && $tutorial['end_sec'] !== null) {
                    if ((int)$tutorial['end_sec'] <= (int)$tutorial['start_sec']) {
                        $validator->errors()->add(
                            "tutorials.{$index}.end_sec",
                            'End time must be greater than start time'
                        );
                    }
                }
            }
        });
    }
}
