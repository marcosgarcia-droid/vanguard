<?php

namespace Tests\Unit\Modules\Operations\Domain\FacialPhotos;

use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatusTransition;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatusTransitionException;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatusTransitionPolicy;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationDecision;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class FacialPhotoStatusTransitionPolicyTest extends TestCase
{
    public function test_it_approves_a_pending_photo_after_approval(): void
    {
        $transition = $this->policy()->transition(
            FacialPhotoStatus::PendingValidation,
            FacialPhotoValidationDecision::Approved,
        );

        $this->assertSame(
            FacialPhotoStatus::PendingValidation,
            $transition->from
        );

        $this->assertSame(
            FacialPhotoStatus::Approved,
            $transition->to
        );

        $this->assertTrue(
            $transition->changed()
        );

        $this->assertTrue(
            $transition->reachedTerminalStatus()
        );

        $this->assertFalse(
            $transition->remainsPendingValidation()
        );
    }

    public function test_it_rejects_a_pending_photo_after_rejection(): void
    {
        $transition = $this->policy()->transition(
            FacialPhotoStatus::PendingValidation,
            FacialPhotoValidationDecision::Rejected,
        );

        $this->assertSame(
            FacialPhotoStatus::Rejected,
            $transition->to
        );

        $this->assertTrue(
            $transition->changed()
        );

        $this->assertTrue(
            $transition->reachedTerminalStatus()
        );
    }

    public function test_it_keeps_an_inconclusive_photo_pending(): void
    {
        $transition = $this->policy()->transition(
            FacialPhotoStatus::PendingValidation,
            FacialPhotoValidationDecision::Inconclusive,
        );

        $this->assertSame(
            FacialPhotoStatus::PendingValidation,
            $transition->to
        );

        $this->assertFalse(
            $transition->changed()
        );

        $this->assertFalse(
            $transition->reachedTerminalStatus()
        );

        $this->assertTrue(
            $transition->remainsPendingValidation()
        );
    }

    public function test_it_refuses_to_revalidate_non_pending_statuses(): void
    {
        foreach (
            [
                FacialPhotoStatus::Approved,
                FacialPhotoStatus::Rejected,
                FacialPhotoStatus::Outdated,
            ] as $status
        ) {
            try {
                $this->policy()->transition(
                    $status,
                    FacialPhotoValidationDecision::Approved,
                );

                $this->fail(
                    'Era esperada uma FacialPhotoStatusTransitionException.'
                );
            } catch (
                FacialPhotoStatusTransitionException $exception
            ) {
                $this->assertStringContainsString(
                    $status->label(),
                    $exception->getMessage()
                );
            }
        }
    }

    public function test_it_serializes_only_a_safe_transition_summary(): void
    {
        $transition = $this->policy()->transition(
            FacialPhotoStatus::PendingValidation,
            FacialPhotoValidationDecision::Rejected,
        );

        $this->assertSame(
            [
                'from' => 'pending_validation',
                'from_label' => 'Aguardando validação',
                'to' => 'rejected',
                'to_label' => 'Reprovada',
                'decision' => 'rejected',
                'decision_label' => 'Reprovada',
                'changed' => true,
            ],
            $transition->toArray()
        );
    }

    public function test_it_rejects_an_inconsistent_direct_transition(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        new FacialPhotoStatusTransition(
            from: FacialPhotoStatus::PendingValidation,
            to: FacialPhotoStatus::Approved,
            decision: FacialPhotoValidationDecision::Rejected,
        );
    }

    public function test_it_rejects_a_transition_from_a_terminal_status(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        new FacialPhotoStatusTransition(
            from: FacialPhotoStatus::Approved,
            to: FacialPhotoStatus::Approved,
            decision: FacialPhotoValidationDecision::Approved,
        );
    }

    private function policy(): FacialPhotoStatusTransitionPolicy
    {
        return new FacialPhotoStatusTransitionPolicy;
    }
}
