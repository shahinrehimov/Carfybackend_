<?php

return [
    'set_default_enable' => env('LANG_SET_DEFAULT_ENABLE', true),
    'cache_enable' => env('LANG_CACHE_ENABLE', true),
    'has_user_lang' => env('LANG_HAS_USER_LANG', false),
    'default_lang' => env('LANG_DEFAULT_LANG', false),
    'cache_language_key' => env('LANG_CACHE_LANGUAGE_KEY', 'cache_languages'),
    'cache_translations_key' => env('LANG_CACHE_TRANSLATIONS_KEY', 'cache_translations'),
];
