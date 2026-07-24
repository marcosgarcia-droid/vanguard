<?php

return [
    'integrations' => [
        'cnpj_lookup' => [
            'providers' => array_values(array_filter(array_map(
                'trim',
                explode(
                    ',',
                    (string) env(
                        'VANGUARD_CNPJ_LOOKUP_PROVIDERS',
                        'brasilapi,receitaws'
                    )
                ),
            ))),

            'brasilapi' => [
                'base_url' => env(
                    'VANGUARD_CNPJ_BRASILAPI_BASE_URL',
                    'https://brasilapi.com.br'
                ),
            ],

            'receitaws' => [
                'base_url' => env(
                    'VANGUARD_CNPJ_RECEITAWS_BASE_URL',
                    'https://www.receitaws.com.br'
                ),
            ],
        ],
    ],

    'operations' => [
        'visits' => [
            'expiration' => [
                'enabled' => (bool) env(
                    'VANGUARD_VISIT_EXPIRATION_ENABLED',
                    false
                ),

                'grace_hours' => max(
                    0,
                    (int) env(
                        'VANGUARD_VISIT_EXPIRATION_GRACE_HOURS',
                        24
                    )
                ),

                'batch_size' => min(
                    1000,
                    max(
                        1,
                        (int) env(
                            'VANGUARD_VISIT_EXPIRATION_BATCH_SIZE',
                            200
                        )
                    )
                ),
            ],
        ],
    ],
];
