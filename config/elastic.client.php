<?php

declare(strict_types=1);

return [
    'default' => env('ELASTIC_CONNECTION', 'default'),

    // Replica count for the product search index (set by product:search-index).
    // Use 0 for a single-node dev cluster; >= 1 in production for availability.
    'index_replicas' => (int) env('ELASTIC_INDEX_REPLICAS', 1),

    'connections' => [
        'default' => array_filter([
            'hosts' => [
                env('ELASTIC_HOST', 'localhost:9200'),
            ],

            // Basic auth (username/password). Leave empty for a local node with
            // security disabled.
            'basicAuthentication' => array_filter([
                env('ELASTIC_USERNAME'),
                env('ELASTIC_PASSWORD'),
            ]) ?: null,

            // API key auth (preferred for the production node). Accepts either an
            // encoded key or "id:key".
            'apiKey' => env('ELASTIC_API_KEY') ?: null,

            // TLS: path to the CA certificate bundle and whether to verify it.
            'caBundle' => env('ELASTIC_CA_BUNDLE') ?: null,
            'sslVerification' => env('ELASTIC_SSL_VERIFICATION', true),
        ], static fn ($value) => $value !== null),
    ],
];
