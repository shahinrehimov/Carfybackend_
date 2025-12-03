<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Slider Settings
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'height' => 800,
        'mobile_height' => 400,
        'auto_play' => true,
        'auto_play_interval' => 5000,
        'show_navigation' => true,
        'show_pagination' => true,
        'show_progress_bar' => false,
        'transition_effect' => 'slide',
        'transition_duration' => 800,
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported Layer Types
    |--------------------------------------------------------------------------
    */
    'layer_types' => [
        'background' => 'Background Layer',
        'image' => 'Image Layer',
        'text' => 'Text Layer',
        'button' => 'Button Layer',
        'video' => 'Video Layer',
        'custom' => 'Custom Layer',
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported Transition Effects
    |--------------------------------------------------------------------------
    */
    'transition_effects' => [
        'slide' => 'Slide',
        'fade' => 'Fade',
        'zoom' => 'Zoom',
        'parallax' => 'Parallax',
    ],

    /*
    |--------------------------------------------------------------------------
    | Animation Presets
    |--------------------------------------------------------------------------
    */
    'animations' => [
        'fade' => [
            'fadeIn', 'fadeInUp', 'fadeInDown', 'fadeInLeft', 'fadeInRight'
        ],
        'slide' => [
            'slideInUp', 'slideInDown', 'slideInLeft', 'slideInRight'
        ],
        'scale' => [
            'zoomIn', 'zoomOut'
        ],
        'bounce' => [
            'bounceIn', 'bounceInUp'
        ],
        'rotate' => [
            'rotateIn', 'flipInX', 'flipInY'
        ],
        'special' => [
            'typewriter', 'parallax'
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Media Settings
    |--------------------------------------------------------------------------
    */
    'media' => [
        'collections' => [
            'slider_images' => [
                'disk' => 'public',
                'path' => 'sliders/images',
                'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
                'max_size' => 10240, // 10MB
            ],
            'slider_videos' => [
                'disk' => 'public',
                'path' => 'sliders/videos',
                'allowed_mime_types' => ['video/mp4', 'video/webm'],
                'max_size' => 51200, // 50MB
            ],
        ],
        'conversions' => [
            'thumb' => [
                'width' => 300,
                'height' => 200,
                'quality' => 80,
            ],
            'medium' => [
                'width' => 800,
                'height' => 600,
                'quality' => 85,
            ],
            'large' => [
                'width' => 1920,
                'height' => 1080,
                'quality' => 90,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Settings
    |--------------------------------------------------------------------------
    */
    'performance' => [
        'cache_duration' => 300, // 5 minutes
        'lazy_loading' => true,
        'preload_priority_slides' => 1,
        'optimize_images' => true,
        'enable_webp' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    */
    'validation' => [
        'max_layers_per_slide' => 20,
        'max_slides_per_slider' => 50,
        'min_layer_size' => 1,
        'max_layer_size' => 100,
        'max_animation_duration' => 5000,
        'max_animation_delay' => 5000,
    ],
];
