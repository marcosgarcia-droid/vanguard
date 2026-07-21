<?php

namespace Tests\Unit\Modules\Operations\Domain\Visits;

use App\Modules\Operations\Domain\Visits\VisitVehicleAuthorizationStatus;
use PHPUnit\Framework\TestCase;

class VisitVehicleAuthorizationStatusTest extends TestCase
{
    public function test_it_exposes_portuguese_labels(): void
    {
        $this->assertSame(
            'Aguardando autorização',
            VisitVehicleAuthorizationStatus::Pending->label()
        );

        $this->assertSame(
            'Entrada autorizada',
            VisitVehicleAuthorizationStatus::Authorized->label()
        );

        $this->assertSame(
            'Entrada recusada',
            VisitVehicleAuthorizationStatus::Rejected->label()
        );
    }

    public function test_only_pending_status_is_pending(): void
    {
        $this->assertTrue(
            VisitVehicleAuthorizationStatus::Pending->isPending()
        );

        $this->assertFalse(
            VisitVehicleAuthorizationStatus::Authorized->isPending()
        );

        $this->assertFalse(
            VisitVehicleAuthorizationStatus::Rejected->isPending()
        );
    }

    public function test_authorized_and_rejected_are_final(): void
    {
        $this->assertFalse(
            VisitVehicleAuthorizationStatus::Pending->isFinal()
        );

        $this->assertTrue(
            VisitVehicleAuthorizationStatus::Authorized->isFinal()
        );

        $this->assertTrue(
            VisitVehicleAuthorizationStatus::Rejected->isFinal()
        );
    }
}
