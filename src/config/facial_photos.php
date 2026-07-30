<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validação técnica do original
    |--------------------------------------------------------------------------
    |
    | Estes critérios são aplicados ao arquivo original capturado ou enviado.
    | Eles não representam os limites da imagem derivada que será transmitida
    | ao equipamento facial.
    |
    */

    'technical_validation' => [
        'version' => 'technical-v1',

        'allowed_mime_types' => [
            'image/jpeg',
            'image/png',
            'image/webp',
        ],

        'maximum_original_size_bytes' => 5 * 1024 * 1024,
        'maximum_pixels' => 20_000_000,

        'minimum_width' => 500,
        'minimum_height' => 500,

        'minimum_height_width_ratio' => 1.0,
        'maximum_height_width_ratio' => 2.0,

        /*
         * Escala de luminosidade: 0 a 255.
         */
        'minimum_mean_luminance' => 45.0,
        'maximum_mean_luminance' => 220.0,

        /*
         * Desvio-padrão da luminosidade da imagem amostrada.
         */
        'minimum_contrast_standard_deviation' => 18.0,

        /*
         * Variância do Laplaciano da imagem em tons de cinza.
         * Este limiar é inicial e deverá ser calibrado com amostras reais.
         */
        'minimum_sharpness_variance' => 80.0,

        /*
         * A análise usa uma cópia reduzida somente em memória.
         */
        'sample_maximum_dimension' => 320,
    ],

    /*
    |--------------------------------------------------------------------------
    | Validação facial por provider
    |--------------------------------------------------------------------------
    |
    | A validação facial permanece desativada até que o ambiente habilite
    | explicitamente a funcionalidade e um provider. Nenhuma destas opções
    | inicia processamento, fila, HTTP ou sincronização automaticamente.
    |
    */

    'validation' => [
        'enabled' => env(
            'VANGUARD_FACIAL_PHOTO_VALIDATION_ENABLED',
            false
        ),

        /*
         * Nenhum provider é selecionado por padrão.
         */
        'provider' => env(
            'VANGUARD_FACIAL_PHOTO_VALIDATION_PROVIDER'
        ),

        'simulator' => [
            /*
             * O simulador utiliza somente resultados sintéticos locais.
             */
            'enabled' => env(
                'VANGUARD_FACIAL_PHOTO_VALIDATION_SIMULATOR_ENABLED',
                false
            ),

            /*
             * Esta lista não é controlada por variável de ambiente.
             * Produção não pode habilitar o simulador por configuração externa.
             */
            'allowed_environments' => [
                'local',
                'testing',
            ],

            /*
             * O cenário padrão é inconclusivo e não aprova uma foto.
             * Ele não é executado automaticamente.
             */
            'default_scenario' => env(
                'VANGUARD_FACIAL_PHOTO_VALIDATION_SIMULATOR_DEFAULT_SCENARIO',
                'validator_unavailable'
            ),
        ],

        /*
         * O provider local permanece inativo até que o serviço de visão
         * computacional seja instalado e validado.
         */
        'local_vision' => [
            'enabled' => env(
                'VANGUARD_FACIAL_PHOTO_VALIDATION_LOCAL_VISION_ENABLED',
                false
            ),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Derivado para os leitores faciais Intelbras
    |--------------------------------------------------------------------------
    |
    | Limites documentados para o conteúdo PhotoData enviado ao equipamento.
    | A geração desse derivado será implementada em bloco posterior. O original
    | privado será preservado.
    |
    */

    'intelbras_derivative' => [
        'mime_type' => 'image/jpeg',

        'minimum_width' => 150,
        'minimum_height' => 300,

        'maximum_width' => 600,
        'maximum_height' => 1200,

        'maximum_height_width_ratio' => 2.0,
        'maximum_size_bytes' => 100 * 1024,

        'recommended_minimum_width' => 500,
        'recommended_minimum_height' => 500,

        'recommended_minimum_face_ratio' => 1 / 3,
        'recommended_maximum_face_ratio' => 2 / 3,
    ],

];
