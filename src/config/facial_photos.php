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
