<?php

namespace Database\Seeders;

use App\Models\Slider;
use App\Models\SliderLayer;
use App\Services\LangService;
use Illuminate\Database\Seeder;

class SliderSeeder extends Seeder
{
    public function run(): void
    {
        $languages = collect(LangService::instance()->getLanguages());

        // Create Hero Slider
        $heroSlider = Slider::createWithLangs([
            'slug' => 'hero-homepage',
            'type' => 'hero',
            'height' => 800,
            'mobile_height' => 600,
            'auto_play' => true,
            'auto_play_interval' => 6000,
            'show_navigation' => true,
            'show_pagination' => true,
            'transition_effect' => 'slide',
            'sort' => 1,
            'is_active' => true,
            'translations' => $languages->map(function ($lang) {
                return [
                    'language_id' => $lang['id'],
                    'title' => 'Title',
                    'description' => 'Description',
                ];
            })->toArray(),
        ]);

        // Create layers for hero slider
        SliderLayer::createWithLangs([
            'slide_id' => $heroSlider->id,
            'type' => 'background',
            'sort' => 1,
            'z_index' => 1,
            'position' => ['x' => 0, 'y' => 0, 'width' => 100, 'height' => 100, 'unit' => '%'],
            'styles' => [
                'background' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'
            ],
            'is_active' => true,
            'translations' => $languages->map(function ($lang) {
                return [
                    'language_id' => $lang['id'],
                    'title' => 'Background Layer',
                ];
            })->toArray(),
        ]);

        SliderLayer::createWithLangs([
            'slide_id' => $heroSlider->id,
            'type' => 'text',
            'sort' => 2,
            'z_index' => 3,
            'position' => ['x' => 10, 'y' => 30, 'width' => 80, 'height' => 20, 'unit' => '%'],
            'animation' => ['type' => 'fadeInUp', 'duration' => 800, 'delay' => 300],
            'styles' => [
                'fontSize' => '3.5rem',
                'fontWeight' => 'bold',
                'color' => '#ffffff',
                'textAlign' => 'center',
                'textShadow' => '2px 2px 4px rgba(0,0,0,0.3)'
            ],
            'is_active' => true,
        ])->updateWithLangs([
            'translations' => $languages->map(function ($lang) {
                return [
                    'language_id' => $lang['id'],
                    'title' => 'Main Title',
                    'content' => 'Welcome to Our Store',
                ];
            })->toArray(),
        ]);

        echo "Slider seeder completed successfully!\n";
    }
}
