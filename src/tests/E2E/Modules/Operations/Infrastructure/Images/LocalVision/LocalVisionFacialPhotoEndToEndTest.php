<?php

declare(strict_types=1);

namespace Tests\E2E\Modules\Operations\Infrastructure\Images\LocalVision;

use App\Modules\Operations\Application\FacialPhotos\Validation\LocalVision\LocalVisionFacialPhotoClient;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationIssue;
use App\Modules\Operations\Infrastructure\Images\LocalVision\Http\LaravelHttpLocalVisionFacialPhotoClient;
use App\Modules\Operations\Infrastructure\Images\LocalVision\IntelbrasLocalVisionFacialPhotoPolicy;
use App\Modules\Operations\Infrastructure\Images\LocalVision\LocalVisionFacialPhotoValidator;
use GdImage;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

final class LocalVisionFacialPhotoEndToEndTest extends TestCase
{
    private string $imagePath;

    protected function setUp(): void
    {
        parent::setUp();

        $token = getenv(
            'VANGUARD_FACIAL_VISION_E2E_TOKEN'
        );

        if (
            ! is_string($token)
            || trim($token) === ''
        ) {
            throw new RuntimeException(
                'O token efêmero do teste E2E não foi configurado.'
            );
        }

        config()->set(
            'facial_photos.validation.local_vision.base_url',
            'http://facial-vision:8000'
        );

        config()->set(
            'facial_photos.validation.local_vision.endpoint',
            '/v1/facial-photo/analyze'
        );

        config()->set(
            'facial_photos.validation.local_vision.token',
            trim($token)
        );

        config()->set(
            'facial_photos.validation.local_vision.connect_timeout_seconds',
            2
        );

        config()->set(
            'facial_photos.validation.local_vision.request_timeout_seconds',
            15
        );

        app()->forgetInstance(
            LocalVisionFacialPhotoClient::class
        );

        $temporaryPath = tempnam(
            sys_get_temp_dir(),
            'vanguard-facial-vision-e2e-'
        );

        if (! is_string($temporaryPath)) {
            throw new RuntimeException(
                'Não foi possível preparar o arquivo sintético.'
            );
        }

        $this->imagePath = "{$temporaryPath}.jpg";

        if (! rename($temporaryPath, $this->imagePath)) {
            @unlink($temporaryPath);

            throw new RuntimeException(
                'Não foi possível preparar o caminho sintético.'
            );
        }

        $this->createSyntheticBlankImage(
            $this->imagePath
        );
    }

    protected function tearDown(): void
    {
        if (
            isset($this->imagePath)
            && is_file($this->imagePath)
        ) {
            unlink($this->imagePath);
        }

        parent::tearDown();
    }

    public function test_it_executes_the_real_internal_http_flow_without_persistence(): void
    {
        $queries = [];

        DB::listen(
            static function (
                QueryExecuted $query
            ) use (&$queries): void {
                $queries[] = $query->sql;
            }
        );

        $client = app(
            LocalVisionFacialPhotoClient::class
        );

        $this->assertInstanceOf(
            LaravelHttpLocalVisionFacialPhotoClient::class,
            $client
        );

        $policy = new IntelbrasLocalVisionFacialPhotoPolicy(
            minimumFaceRatio: (float) config(
                'facial_photos.intelbras_derivative.recommended_minimum_face_ratio'
            ),
            maximumFaceRatio: (float) config(
                'facial_photos.intelbras_derivative.recommended_maximum_face_ratio'
            ),
        );

        $result = (
            new LocalVisionFacialPhotoValidator(
                client: $client,
                policy: $policy,
            )
        )->validate($this->imagePath);

        $this->assertTrue($result->isRejected());
        $this->assertFalse($result->isApproved());
        $this->assertFalse($result->isInconclusive());

        $this->assertSame(
            LocalVisionFacialPhotoValidator::VALIDATOR,
            $result->validator
        );

        $this->assertSame(
            IntelbrasLocalVisionFacialPhotoPolicy::VERSION,
            $result->version
        );

        $this->assertSame(0, $result->faceCount);

        $this->assertTrue(
            $result->hasIssue(
                FacialPhotoValidationIssue::NoFaceDetected
            )
        );

        /*
         * Esta é a allowlist completa do contrato recebido pelo
         * FacialPhotoValidationResult. Valores como o engine_version
         * podem conter "face-landmarker" legitimamente. Portanto,
         * validamos nomes de chaves, nunca substrings de valores.
         */
        $expectedMetricKeys = [
            'available',
            'transport_configured',
            'policy_configured',
            'approval_calibrated',
            'service_version',
            'engine',
            'engine_version',
            'image_width',
            'image_height',
            'face_ratio',
            'center_offset_x',
            'center_offset_y',
            'yaw_degrees',
            'pitch_degrees',
            'roll_degrees',
            'left_eye_open_score',
            'right_eye_open_score',
            'centered',
            'frontal',
            'eyes_open',
            'occluded',
            'inference_ms',
        ];

        $actualMetricKeys = array_keys(
            $result->metrics
        );

        sort($expectedMetricKeys);
        sort($actualMetricKeys);

        $this->assertSame(
            $expectedMetricKeys,
            $actualMetricKeys,
            'O E2E deve transportar somente métricas escalares autorizadas.'
        );

        $this->assertTrue(
            $result->metrics['available']
        );

        $this->assertTrue(
            $result->metrics['transport_configured']
        );

        $this->assertTrue(
            $result->metrics['policy_configured']
        );

        $this->assertFalse(
            $result->metrics['approval_calibrated']
        );

        $this->assertSame(
            'foundation-v1',
            $result->metrics['service_version']
        );

        $this->assertSame(
            'mediapipe-opencv',
            $result->metrics['engine']
        );

        $this->assertIsString(
            $result->metrics['engine_version']
        );

        $this->assertNotSame(
            '',
            trim($result->metrics['engine_version'])
        );

        $this->assertSame(
            512,
            $result->metrics['image_width']
        );

        $this->assertSame(
            512,
            $result->metrics['image_height']
        );

        $this->assertNull(
            $result->metrics['face_ratio']
        );

        $this->assertNull(
            $result->metrics['center_offset_x']
        );

        $this->assertNull(
            $result->metrics['center_offset_y']
        );

        $this->assertNull(
            $result->metrics['yaw_degrees']
        );

        $this->assertNull(
            $result->metrics['pitch_degrees']
        );

        $this->assertNull(
            $result->metrics['roll_degrees']
        );

        $this->assertNull(
            $result->metrics['left_eye_open_score']
        );

        $this->assertNull(
            $result->metrics['right_eye_open_score']
        );

        $this->assertNull(
            $result->metrics['centered']
        );

        $this->assertNull(
            $result->metrics['frontal']
        );

        $this->assertNull(
            $result->metrics['eyes_open']
        );

        $this->assertNull(
            $result->metrics['occluded']
        );

        $inferenceMilliseconds =
            $result->metrics['inference_ms'];

        $this->assertTrue(
            is_int($inferenceMilliseconds)
            || is_float($inferenceMilliseconds)
        );

        $this->assertGreaterThanOrEqual(
            0,
            $inferenceMilliseconds
        );

        foreach (
            $result->metrics as $metricValue
        ) {
            $this->assertTrue(
                is_null($metricValue)
                || is_bool($metricValue)
                || is_int($metricValue)
                || is_float($metricValue)
                || is_string($metricValue),
                'O contrato E2E não pode transportar estruturas complexas.'
            );
        }

        foreach (
            [
                'landmarks',
                'face_landmarks',
                'blendshapes',
                'face_blendshapes',
                'transformation_matrix',
                'transformation_matrices',
                'embedding',
                'embeddings',
                'template',
                'biometric_template',
                'raw_payload',
                'face_crop',
                'image',
                'image_bytes',
            ] as $forbiddenMetricKey
        ) {
            $this->assertArrayNotHasKey(
                $forbiddenMetricKey,
                $result->metrics
            );
        }

        $this->assertSame(
            [],
            $queries,
            'O E2E direto não pode consultar nem alterar o banco.'
        );
    }

    private function createSyntheticBlankImage(
        string $path
    ): void {
        $image = imagecreatetruecolor(
            512,
            512
        );

        if (! $image instanceof GdImage) {
            throw new RuntimeException(
                'Não foi possível criar a imagem sintética.'
            );
        }

        try {
            $white = imagecolorallocate(
                $image,
                255,
                255,
                255
            );

            if ($white === false) {
                throw new RuntimeException(
                    'Não foi possível preparar o fundo sintético.'
                );
            }

            if (
                ! imagefill(
                    $image,
                    0,
                    0,
                    $white
                )
            ) {
                throw new RuntimeException(
                    'Não foi possível preencher a imagem sintética.'
                );
            }

            if (! imagejpeg($image, $path, 90)) {
                throw new RuntimeException(
                    'Não foi possível salvar a imagem sintética.'
                );
            }
        } finally {
            imagedestroy($image);
        }
    }
}
