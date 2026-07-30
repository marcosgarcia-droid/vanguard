<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialPhotos\Validation\LocalVision;

use InvalidArgumentException;

final readonly class LocalVisionFacialPhotoAnalysis
{
    public const SCHEMA_VERSION = '1.0';

    public const SERVICE = 'vanguard-facial-vision';

    /**
     * Campos expressamente autorizados no retorno do serviço.
     *
     * @var list<string>
     */
    private const ALLOWED_METRICS = [
        'detection_confidence',
        'face_ratio',
        'center_offset_x',
        'center_offset_y',
        'yaw_degrees',
        'pitch_degrees',
        'roll_degrees',
        'left_eye_open_probability',
        'right_eye_open_probability',
        'occlusion_score',
        'brightness',
        'contrast',
        'sharpness',
        'centered',
        'frontal',
        'eyes_open',
        'occluded',
    ];

    /**
     * @param  array<string, bool|int|float|string|null>  $metrics
     */
    public function __construct(
        public string $serviceVersion,
        public string $engine,
        public string $engineVersion,
        public int $faceCount,
        public array $metrics,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(
        array $payload
    ): self {
        if (array_is_list($payload)) {
            throw new InvalidArgumentException(
                'A resposta de visão facial deve ser um objeto JSON.'
            );
        }

        if (
            self::requiredString($payload, 'schema_version', 16)
                !== self::SCHEMA_VERSION
        ) {
            throw new InvalidArgumentException(
                'A versão do contrato facial não é suportada.'
            );
        }

        if (
            self::requiredString($payload, 'service', 64)
                !== self::SERVICE
        ) {
            throw new InvalidArgumentException(
                'O identificador do serviço facial é inválido.'
            );
        }

        $faceCount = self::requiredInteger(
            $payload,
            'face_count'
        );

        if ($faceCount < 0 || $faceCount > 10) {
            throw new InvalidArgumentException(
                'A quantidade de rostos retornada é inválida.'
            );
        }

        $metrics = $payload['metrics'] ?? null;

        if (! is_array($metrics)) {
            throw new InvalidArgumentException(
                'As métricas do serviço facial são obrigatórias.'
            );
        }

        return new self(
            serviceVersion: self::requiredString(
                $payload,
                'service_version',
                64
            ),
            engine: self::requiredString(
                $payload,
                'engine',
                64
            ),
            engineVersion: self::requiredString(
                $payload,
                'engine_version',
                64
            ),
            faceCount: $faceCount,
            metrics: self::sanitizeMetrics($metrics),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function requiredString(
        array $payload,
        string $key,
        int $maximumLength,
    ): string {
        $value = $payload[$key] ?? null;

        if (
            ! is_string($value)
            || trim($value) === ''
            || strlen($value) > $maximumLength
        ) {
            throw new InvalidArgumentException(
                "O campo {$key} da resposta facial é inválido."
            );
        }

        return trim($value);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function requiredInteger(
        array $payload,
        string $key,
    ): int {
        $value = $payload[$key] ?? null;

        if (! is_int($value)) {
            throw new InvalidArgumentException(
                "O campo {$key} da resposta facial é inválido."
            );
        }

        return $value;
    }

    /**
     * @param  array<array-key, mixed>  $metrics
     * @return array<string, bool|int|float|string|null>
     */
    private static function sanitizeMetrics(
        array $metrics
    ): array {
        $sanitized = [];

        foreach (self::ALLOWED_METRICS as $allowedMetric) {
            if (! array_key_exists($allowedMetric, $metrics)) {
                continue;
            }

            $value = $metrics[$allowedMetric];

            if (
                $value !== null
                && ! is_bool($value)
                && ! is_int($value)
                && ! is_float($value)
                && ! is_string($value)
            ) {
                throw new InvalidArgumentException(
                    "A métrica {$allowedMetric} possui um valor inválido."
                );
            }

            $sanitized[$allowedMetric] = $value;
        }

        return $sanitized;
    }
}
