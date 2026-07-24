<?php

namespace Tests\Unit\Modules\Operations\Domain\FacialPhotos;

use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoSource;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;
use PHPUnit\Framework\TestCase;

final class FacialPhotoEnumsTest extends TestCase
{
    public function test_it_exposes_portuguese_status_options(): void
    {
        $this->assertSame(
            [
                'pending_validation' => 'Aguardando validação',
                'approved' => 'Aprovada',
                'rejected' => 'Reprovada',
                'outdated' => 'Desatualizada',
            ],
            FacialPhotoStatus::options()
        );

        $this->assertTrue(
            FacialPhotoStatus::Approved
                ->isUsableForRecognition()
        );

        $this->assertFalse(
            FacialPhotoStatus::PendingValidation
                ->isUsableForRecognition()
        );
    }

    public function test_it_exposes_supported_capture_sources(): void
    {
        $this->assertSame(
            [
                'webcam' => 'Webcam',
                'file_upload' => 'Arquivo enviado',
                'facial_device' => 'Equipamento facial',
                'import' => 'Importação',
            ],
            FacialPhotoSource::options()
        );
    }
}
