<?php

namespace Tests\Unit\Modules\Operations\UI\Filament\Resources\VisitorRecords;

use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use App\Modules\Operations\UI\Filament\Resources\VisitorRecords\Schemas\VisitorFacialPhotoStatusPresentation;
use Tests\TestCase;

final class VisitorFacialPhotoStatusPresentationTest extends TestCase
{
    public function test_it_presents_a_missing_photo_in_gray(): void
    {
        $record = new VisitorRecord;

        $record->setRelation(
            'latestFacialPhoto',
            null
        );

        $this->assertSame(
            [
                'label' => 'Não cadastrada',
                'color' => 'gray',
            ],
            VisitorFacialPhotoStatusPresentation::summary(
                $record
            )
        );
    }

    public function test_it_presents_pending_validation_as_warning(): void
    {
        $this->assertPresentation(
            FacialPhotoStatus::PendingValidation,
            'Aguardando validação',
            'warning'
        );
    }

    public function test_it_presents_an_approved_photo_as_success(): void
    {
        $this->assertPresentation(
            FacialPhotoStatus::Approved,
            'Aprovada',
            'success'
        );
    }

    public function test_it_presents_a_rejected_photo_as_danger(): void
    {
        $this->assertPresentation(
            FacialPhotoStatus::Rejected,
            'Reprovada',
            'danger'
        );
    }

    public function test_it_presents_an_outdated_photo_in_gray(): void
    {
        $this->assertPresentation(
            FacialPhotoStatus::Outdated,
            'Desatualizada',
            'gray'
        );
    }

    public function test_the_visitor_infolist_uses_the_safe_presentation(): void
    {
        $source = file_get_contents(
            app_path(
                'Modules/Operations/UI/Filament/Resources/'
                .'VisitorRecords/Schemas/'
                .'VisitorRecordInfolist.php'
            )
        );

        $this->assertIsString(
            $source
        );

        foreach ([
            "TextEntry::make('facial_photo_status')",
            "->label('Situação da foto facial')",
            'VisitorFacialPhotoStatusPresentation::summary(',
            "['label']",
            "['color']",
        ] as $expected) {
            $this->assertStringContainsString(
                $expected,
                $source
            );
        }

        foreach ([
            'validation_result',
            'rejection_reasons',
            'metrics',
            'issues',
            'sha256',
        ] as $sensitiveField) {
            $this->assertStringNotContainsString(
                $sensitiveField,
                $source
            );
        }
    }

    private function assertPresentation(
        FacialPhotoStatus $status,
        string $label,
        string $color
    ): void {
        $record = new VisitorRecord;

        $record->setRelation(
            'latestFacialPhoto',
            new FacialPhotoRecord([
                'status' => $status,
            ])
        );

        $this->assertSame(
            [
                'label' => $label,
                'color' => $color,
            ],
            VisitorFacialPhotoStatusPresentation::summary(
                $record
            )
        );
    }
}
