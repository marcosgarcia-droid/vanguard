<?php

namespace App\Modules\Operations\UI\Filament\Forms\Components;

use Filament\Forms\Components\Field;

final class FacialPhotoCapture extends Field
{
    protected string $view =
        'filament.forms.components.facial-photo-capture';

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->dehydrated()
            ->nullable();
    }
}
