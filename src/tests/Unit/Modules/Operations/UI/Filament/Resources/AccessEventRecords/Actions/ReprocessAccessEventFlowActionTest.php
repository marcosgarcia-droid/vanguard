<?php

namespace Tests\Unit\Modules\Operations\UI\Filament\Resources\AccessEventRecords\Actions;

use App\Modules\Operations\UI\Filament\Resources\AccessEventRecords\Actions\ReprocessAccessEventFlowAction;
use Filament\Actions\Action;
use ReflectionClass;
use Tests\TestCase;

class ReprocessAccessEventFlowActionTest extends TestCase
{
    public function test_it_creates_the_reprocess_action(): void
    {
        $action =
            ReprocessAccessEventFlowAction::make();

        $this->assertInstanceOf(
            Action::class,
            $action
        );

        $this->assertSame(
            'reprocessAccessEventFlow',
            $action->getName()
        );
    }

    public function test_it_authorizes_orchestrates_and_audits_the_reprocessing(): void
    {
        $filename = (
            new ReflectionClass(
                ReprocessAccessEventFlowAction::class
            )
        )->getFileName();

        $this->assertIsString($filename);

        $source = file_get_contents($filename);

        $this->assertIsString($source);

        foreach ([
            'Gate::authorize(',
            "'reprocessFlow'",
            'ContinueAccessEventFlowUseCase::class',
            'ContinueAccessEventFlowCommand(',
            "->event(\n                'access_event_flow_reprocessed'",
            "activity(\n            'access_control'",
        ] as $expected) {
            $this->assertStringContainsString(
                $expected,
                $source
            );
        }

        $this->assertStringNotContainsString(
            'Http::',
            $source
        );

        $this->assertStringNotContainsString(
            'raw_payload',
            $source
        );
    }
}
