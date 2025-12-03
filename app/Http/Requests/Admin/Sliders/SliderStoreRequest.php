<?php

namespace App\Http\Requests\Admin\Sliders;

use App\Rules\FirstTranslationRequired;
use App\Rules\NestedDistinctRule;
use App\Services\LangService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class SliderStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'slug' => ['nullable', 'string', 'max:10000'],
            'sort' => ['nullable', 'integer', 'min:0'],
            'type' => ['required', 'string', 'in:hero,banner,gallery,custom'],
            'height' => ['nullable', 'integer', 'min:100', 'max:2000'],
            'mobile_height' => ['nullable', 'integer', 'min:100', 'max:1000'],
            'auto_play' => ['nullable', 'boolean'],
            'auto_play_interval' => ['nullable', 'integer', 'min:1000', 'max:30000'],
            'show_navigation' => ['nullable', 'boolean'],
            'show_pagination' => ['nullable', 'boolean'],
            'show_progress_bar' => ['nullable', 'boolean'],
            'transition_effect' => ['nullable', 'string', 'in:slide,fade,zoom,parallax'],
            'transition_duration' => ['nullable', 'integer', 'min:200', 'max:3000'],
            'settings' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],

            // Translations
            'translations' => ['required', 'array', 'size:' . count(LangService::instance()->getLanguages())],
            'translations.*.language_id' => ['required', 'integer', 'distinct', Rule::in(data_get(LangService::instance()->getLanguages(), '*.id'))],
            'translations.*.title' => [new FirstTranslationRequired('title'), 'string', 'max:255'],
            'translations.*.description' => ['nullable', 'string', 'max:500'],

            // Layers
            'layers' => ['nullable', 'array'],
            'layers.*.image' => [
                'filled',
                File::image()->max(10 * 1024),
            ],
            'layers.*.video' => [
                'filled',
                File::types(['mp4', 'webm', 'mov'])->max(100 * 1024),
            ],
            'layers.*.type' => ['required', 'string', 'in:background,image,text,button,video,custom'],
            'layers.*.sort' => ['nullable', 'integer', 'min:0'],
            'layers.*.z_index' => ['nullable', 'integer', 'min:1', 'max:100'],
            'layers.*.position' => ['required', 'array'],
            'layers.*.position.x' => ['required', 'numeric'],
            'layers.*.position.y' => ['required', 'numeric'],
            'layers.*.position.width' => ['required', 'numeric', 'min:1'],
            'layers.*.position.height' => ['required', 'numeric', 'min:1'],
            'layers.*.position.unit' => ['nullable', 'string', 'in:px,%,vw,vh,rem,em'],
            'layers.*.mobile_position' => ['nullable', 'array'],
            'layers.*.animation' => ['nullable', 'array'],
            'layers.*.animation.type' => ['nullable', 'string'],
            'layers.*.animation.duration' => ['nullable', 'integer', 'min:100', 'max:5000'],
            'layers.*.animation.delay' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'layers.*.styles' => ['nullable', 'array'],
            'layers.*.mobile_styles' => ['nullable', 'array'],
            'layers.*.is_active' => ['nullable', 'boolean'],

            // Layer translations
            'layers.*.translations' => ['required', 'array', 'size:' . count(LangService::instance()->getLanguages())],
            'layers.*.translations.*.language_id' => ['required', 'integer', new NestedDistinctRule(1), Rule::in(data_get(LangService::instance()->getLanguages(), '*.id'))],
            'layers.*.translations.*.title' => ['nullable', 'string', 'max:255'],
            'layers.*.translations.*.subtitle' => ['nullable', 'string', 'max:255'],
            'layers.*.translations.*.content' => ['nullable', 'string'],
            'layers.*.translations.*.button_text' => ['nullable', 'string', 'max:100'],
            'layers.*.translations.*.button_url' => ['nullable', 'string', 'max:500'],
            'layers.*.translations.*.alt_text' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Set defaults
        if (!$this->has('sort')) {
            $this->merge(['sort' => 0]);
        }

        if (!$this->has('height')) {
            $this->merge(['height' => 800]);
        }

        if (!$this->has('mobile_height')) {
            $this->merge(['mobile_height' => 400]);
        }

        if (!$this->has('auto_play')) {
            $this->merge(['auto_play' => true]);
        }

        if (!$this->has('auto_play_interval')) {
            $this->merge(['auto_play_interval' => 5000]);
        }

        if (!$this->has('transition_duration')) {
            $this->merge(['transition_duration' => 800]);
        }

        // Set layer defaults
        if ($this->has('layers')) {
            $layers = $this->input('layers');
            foreach ($layers as $key => $layer) {
                if (!isset($layer['sort'])) {
                    $layers[$key]['sort'] = $key;
                }
                if (!isset($layer['z_index'])) {
                    $layers[$key]['z_index'] = $key + 1;
                }
                if (!isset($layer['position']['unit'])) {
                    $layers[$key]['position']['unit'] = '%';
                }
                if (!isset($layer['is_active'])) {
                    $layers[$key]['is_active'] = true;
                }
            }
            $this->merge(['layers' => $layers]);
        }
    }
}
