<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => ':attribute qəbul edilməlidir.',
    'accepted_if' => ':other :value olduqda :attribute qəbul edilməlidir.',
    'active_url' => ':attribute etibarlı URL olmalıdır.',
    'after' => ':attribute :date tarixindən sonra olmalıdır.',
    'after_or_equal' => ':attribute :date tarixinə bərabər və ya sonra olmalıdır.',
    'alpha' => ':attribute yalnız hərflərdən ibarət olmalıdır.',
    'alpha_dash' => ':attribute yalnız hərflər, rəqəmlər, tire və alt xəttdən ibarət olmalıdır.',
    'alpha_num' => ':attribute yalnız hərflər və rəqəmlərdən ibarət olmalıdır.',
    'array' => ':attribute massiv olmalıdır.',
    'ascii' => ':attribute yalnız tək baytlı alfanumerik simvollar və simvollardan ibarət olmalıdır.',
    'before' => ':attribute :date tarixindən əvvəl olmalıdır.',
    'before_or_equal' => ':attribute :date tarixinə bərabər və ya əvvəl olmalıdır.',
    'between' => [
        'array' => ':attribute :min ilə :max arasında elementə malik olmalıdır.',
        'file' => ':attribute :min ilə :max kilobayt arasında olmalıdır.',
        'numeric' => ':attribute :min ilə :max arasında olmalıdır.',
        'string' => ':attribute :min ilə :max simvol arasında olmalıdır.',
    ],
    'boolean' => ':attribute doğru və ya yanlış olmalıdır.',
    'can' => ':attribute icazəsiz dəyər ehtiva edir.',
    'confirmed' => ':attribute təsdiqi uyğun gəlmir.',
    'contains' => ':attribute tələb olunan dəyəri əldə etməyib.',
    'current_password' => 'Şifrə yanlışdır.',
    'date' => ':attribute etibarlı tarix olmalıdır.',
    'date_equals' => ':attribute :date tarixinə bərabər olmalıdır.',
    'date_format' => ':attribute :format formatına uyğun olmalıdır.',
    'decimal' => ':attribute :decimal onluq yerə malik olmalıdır.',
    'declined' => ':attribute rədd edilməlidir.',
    'declined_if' => ':other :value olduqda :attribute rədd edilməlidir.',
    'different' => ':attribute və :other fərqli olmalıdır.',
    'digits' => ':attribute :digits rəqəm olmalıdır.',
    'digits_between' => ':attribute :min ilə :max rəqəm arasında olmalıdır.',
    'dimensions' => ':attribute etibarsız şəkil ölçülərinə malikdir.',
    'distinct' => ':attribute təkrarlanan dəyərə malikdir.',
    'doesnt_end_with' => ':attribute aşağıdakılardan biri ilə bitməməlidir: :values.',
    'doesnt_start_with' => ':attribute aşağıdakılardan biri ilə başlamamalıdır: :values.',
    'email' => ':attribute etibarlı e-poçt ünvanı olmalıdır.',
    'ends_with' => ':attribute aşağıdakılardan biri ilə bitməlidir: :values.',
    'enum' => 'Seçilmiş :attribute etibarsızdır.',
    'exists' => 'Seçilmiş :attribute etibarsızdır.',
    'extensions' => ':attribute aşağıdakı genişləmələrdən birinə malik olmalıdır: :values.',
    'file' => ':attribute fayl olmalıdır.',
    'filled' => ':attribute dəyərə malik olmalıdır.',
    'gt' => [
        'array' => ':attribute :value elementdən çox olmalıdır.',
        'file' => ':attribute :value kilobaytdan böyük olmalıdır.',
        'numeric' => ':attribute :value-dən böyük olmalıdır.',
        'string' => ':attribute :value simvoldan çox olmalıdır.',
    ],
    'gte' => [
        'array' => ':attribute :value element və ya daha çox olmalıdır.',
        'file' => ':attribute :value kilobayta bərabər və ya daha böyük olmalıdır.',
        'numeric' => ':attribute :value-ə bərabər və ya daha böyük olmalıdır.',
        'string' => ':attribute :value simvola bərabər və ya daha çox olmalıdır.',
    ],
    'hex_color' => ':attribute etibarlı onaltılıq rəng olmalıdır.',
    'image' => ':attribute şəkil olmalıdır.',
    'in' => 'Seçilmiş :attribute etibarsızdır.',
    'in_array' => ':attribute :other-də mövcud olmalıdır.',
    'integer' => ':attribute tam ədəd olmalıdır.',
    'ip' => ':attribute etibarlı IP ünvanı olmalıdır.',
    'ipv4' => ':attribute etibarlı IPv4 ünvanı olmalıdır.',
    'ipv6' => ':attribute etibarlı IPv6 ünvanı olmalıdır.',
    'json' => ':attribute etibarlı JSON sətri olmalıdır.',
    'list' => ':attribute siyahı olmalıdır.',
    'lowercase' => ':attribute kiçik hərflərlə yazılmalıdır.',
    'lt' => [
        'array' => ':attribute :value elementdən az olmalıdır.',
        'file' => ':attribute :value kilobaytdan az olmalıdır.',
        'numeric' => ':attribute :value-dən az olmalıdır.',
        'string' => ':attribute :value simvoldan az olmalıdır.',
    ],
    'lte' => [
        'array' => ':attribute :value elementdən çox olmamalıdır.',
        'file' => ':attribute :value kilobayta bərabər və ya daha az olmalıdır.',
        'numeric' => ':attribute :value-ə bərabər və ya daha az olmalıdır.',
        'string' => ':attribute :value simvola bərabər və ya daha az olmalıdır.',
    ],
    'mac_address' => ':attribute etibarlı MAC ünvanı olmalıdır.',
    'max' => [
        'array' => ':attribute :max elementdən çox olmamalıdır.',
        'file' => ':attribute :max kilobaytdan böyük olmamalıdır.',
        'numeric' => ':attribute :max-dan böyük olmamalıdır.',
        'string' => ':attribute :max simvoldan çox olmamalıdır.',
    ],
    'max_digits' => ':attribute :max rəqəmdən çox olmamalıdır.',
    'mimes' => ':attribute növü :values olan fayl olmalıdır.',
    'mimetypes' => ':attribute növü :values olan fayl olmalıdır.',
    'min' => [
        'array' => ':attribute ən azı :min elementə malik olmalıdır.',
        'file' => ':attribute ən azı :min kilobayt olmalıdır.',
        'numeric' => ':attribute ən azı :min olmalıdır.',
        'string' => ':attribute ən azı :min simvol olmalıdır.',
    ],
    'min_digits' => ':attribute ən azı :min rəqəmə malik olmalıdır.',
    'missing' => ':attribute əksik olmalıdır.',
    'missing_if' => ':other :value olduqda :attribute əksik olmalıdır.',
    'missing_unless' => ':other :values-də olmadıqda :attribute əksik olmalıdır.',
    'missing_with' => ':values mövcud olduqda :attribute əksik olmalıdır.',
    'missing_with_all' => ':values mövcud olduqda :attribute əksik olmalıdır.',
    'multiple_of' => ':attribute :value-nin qatı olmalıdır.',
    'not_in' => 'Seçilmiş :attribute etibarsızdır.',
    'not_regex' => ':attribute formatı etibarsızdır.',
    'numeric' => ':attribute rəqəm olmalıdır.',
    'password' => [
        'letters' => ':attribute ən azı bir hərf ehtiva etməlidir.',
        'mixed' => ':attribute ən azı bir böyük və bir kiçik hərf ehtiva etməlidir.',
        'numbers' => ':attribute ən azı bir rəqəm ehtiva etməlidir.',
        'symbols' => ':attribute ən azı bir simvol ehtiva etməlidir.',
        'uncompromised' => 'Verilmiş :attribute məlumat sızıntısında görünmüşdür. Zəhmət olmasa fərqli :attribute seçin.',
    ],
    'present' => ':attribute mövcud olmalıdır.',
    'present_if' => ':other :value olduqda :attribute mövcud olmalıdır.',
    'present_unless' => ':other :values-də olmadıqda :attribute mövcud olmalıdır.',
    'present_with' => ':values mövcud olduqda :attribute mövcud olmalıdır.',
    'present_with_all' => ':values mövcud olduqda :attribute mövcud olmalıdır.',
    'prohibited' => ':attribute qadağandır.',
    'prohibited_if' => ':other :value olduqda :attribute qadağandır.',
    'prohibited_unless' => ':other :values-də olmadıqda :attribute qadağandır.',
    'prohibits' => ':attribute :other-in mövcud olmasını qadağan edir.',
    'regex' => ':attribute formatı etibarsızdır.',
    'required' => ':attribute tələb olunur.',
    'required_array_keys' => ':attribute aşağıdakılar üçün girişlər ehtiva etməlidir: :values.',
    'required_if' => ':other :value olduqda :attribute tələb olunur.',
    'required_if_accepted' => ':other qəbul edildikdə :attribute tələb olunur.',
    'required_if_declined' => ':other rədd edildikdə :attribute tələb olunur.',
    'required_unless' => ':other :values-də olmadıqda :attribute tələb olunur.',
    'required_with' => ':values mövcud olduqda :attribute tələb olunur.',
    'required_with_all' => ':values mövcud olduqda :attribute tələb olunur.',
    'required_without' => ':values mövcud olmadıqda :attribute tələb olunur.',
    'required_without_all' => ':values-dən heç biri mövcud olmadıqda :attribute tələb olunur.',
    'same' => ':attribute :other ilə uyğun olmalıdır.',
    'size' => [
        'array' => ':attribute :size element ehtiva etməlidir.',
        'file' => ':attribute :size kilobayt olmalıdır.',
        'numeric' => ':attribute :size olmalıdır.',
        'string' => ':attribute :size simvol olmalıdır.',
    ],
    'starts_with' => ':attribute aşağıdakılardan biri ilə başlamalıdır: :values.',
    'string' => ':attribute sətir olmalıdır.',
    'timezone' => ':attribute etibarlı saat qurşağı olmalıdır.',
    'unique' => ':attribute artıq götürülmüşdür.',
    'uploaded' => ':attribute yükləmək alınmadı.',
    'uppercase' => ':attribute böyük hərflərlə yazılmalıdır.',
    'url' => ':attribute etibarlı URL olmalıdır.',
    'ulid' => ':attribute etibarlı ULID olmalıdır.',
    'uuid' => ':attribute etibarlı UUID olmalıdır.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [],

];
