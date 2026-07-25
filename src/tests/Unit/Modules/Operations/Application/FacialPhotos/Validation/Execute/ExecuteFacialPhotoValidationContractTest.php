<?php

namespace Tests\Unit\Modules\Operations\Application\FacialPhotos\Validation\Execute;

use App\Modules\Operations\Application\FacialPhotos\Validation\Execute\ExecuteFacialPhotoValidationCommand;
use App\Modules\Operations\Application\FacialPhotos\Validation\Execute\ExecuteFacialPhotoValidationException;
use App\Modules\Operations\Application\FacialPhotos\Validation\Execute\ExecuteFacialPhotoValidationResult;
use App\Modules\Operations\Application\FacialPhotos\Validation\Execute\FacialPhotoValidationPersistenceData;
use App\Modules\Operations\Application\FacialPhotos\Validation\Execute\FacialPhotoValidationTarget;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatusTransitionPolicy;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationDecision;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationResult;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ExecuteFacialPhotoValidationContractTest extends TestCase
{
    public function test_it_rejects_invalid_command_identifiers(): void
    {
        $this->assertInvalid(
            static fn (): ExecuteFacialPhotoValidationCommand => new ExecuteFacialPhotoValidationCommand(
                photoId: ' ',
            ),
            'O identificador da foto facial é obrigatório.'
        );

        $this->assertInvalid(
            static fn (): ExecuteFacialPhotoValidationCommand => new ExecuteFacialPhotoValidationCommand(
                photoId: 'photo-1',
                operatorUserId: 0,
            ),
            'O identificador do operador facial é inválido.'
        );
    }

    public function test_it_rejects_invalid_validation_targets(): void
    {
        $this->assertInvalid(
            static fn (): FacialPhotoValidationTarget => new FacialPhotoValidationTarget(
                photoId: '',
                status: FacialPhotoStatus::PendingValidation,
                mediaId: 1,
                absolutePath: '/tmp/photo.jpg',
                sha256: str_repeat('a', 64),
            ),
            'O alvo da validação facial exige uma foto.'
        );

        $this->assertInvalid(
            static fn (): FacialPhotoValidationTarget => new FacialPhotoValidationTarget(
                photoId: 'photo-1',
                status: FacialPhotoStatus::PendingValidation,
                mediaId: 0,
                absolutePath: '/tmp/photo.jpg',
                sha256: str_repeat('a', 64),
            ),
            'O alvo da validação facial exige uma mídia válida.'
        );

        $this->assertInvalid(
            static fn (): FacialPhotoValidationTarget => new FacialPhotoValidationTarget(
                photoId: 'photo-1',
                status: FacialPhotoStatus::PendingValidation,
                mediaId: 1,
                absolutePath: 'relative/photo.jpg',
                sha256: str_repeat('a', 64),
            ),
            'O alvo da validação facial exige um caminho absoluto.'
        );

        $this->assertInvalid(
            static fn (): FacialPhotoValidationTarget => new FacialPhotoValidationTarget(
                photoId: 'photo-1',
                status: FacialPhotoStatus::PendingValidation,
                mediaId: 1,
                absolutePath: '/tmp/photo.jpg',
                sha256: 'invalid',
            ),
            'O alvo da validação facial exige um hash SHA-256 válido.'
        );
    }

    public function test_it_preserves_validation_persistence_data(): void
    {
        $target = $this->target();
        $validation = $this->approvedValidation();

        $transition =
            (new FacialPhotoStatusTransitionPolicy)
                ->transition(
                    currentStatus: $target->status,
                    decision: $validation->decision,
                );

        $data = new FacialPhotoValidationPersistenceData(
            target: $target,
            validation: $validation,
            transition: $transition,
            operatorUserId: 42,
        );

        $this->assertSame($target, $data->target);
        $this->assertSame($validation, $data->validation);
        $this->assertSame($transition, $data->transition);
        $this->assertSame(42, $data->operatorUserId);
    }

    public function test_it_rejects_a_transition_from_another_prepared_status(): void
    {
        $validation = $this->approvedValidation();

        $transition =
            (new FacialPhotoStatusTransitionPolicy)
                ->transition(
                    currentStatus: FacialPhotoStatus::PendingValidation,
                    decision: $validation->decision,
                );

        $target = new FacialPhotoValidationTarget(
            photoId: 'photo-1',
            status: FacialPhotoStatus::Approved,
            mediaId: 10,
            absolutePath: '/tmp/synthetic-photo.jpg',
            sha256: str_repeat('a', 64),
        );

        $this->assertInvalid(
            static fn (): FacialPhotoValidationPersistenceData => new FacialPhotoValidationPersistenceData(
                target: $target,
                validation: $validation,
                transition: $transition,
            ),
            'A transição não parte da situação preparada para a foto facial.'
        );
    }

    public function test_it_exposes_execution_result_helpers(): void
    {
        $validation = $this->approvedValidation();

        $transition =
            (new FacialPhotoStatusTransitionPolicy)
                ->transition(
                    currentStatus: FacialPhotoStatus::PendingValidation,
                    decision: $validation->decision,
                );

        $result = new ExecuteFacialPhotoValidationResult(
            photoId: 'photo-1',
            attemptId: 'attempt-1',
            attemptNumber: 1,
            validation: $validation,
            transition: $transition,
            validatedAt: new DateTimeImmutable(
                '2026-07-25 17:00:00'
            ),
        );

        $this->assertTrue($result->isApproved());
        $this->assertFalse($result->isRejected());
        $this->assertFalse($result->isInconclusive());
        $this->assertTrue($result->changedStatus());
        $this->assertSame(1, $result->attemptNumber);
    }

    public function test_it_exposes_safe_execution_exceptions(): void
    {
        $previous = new RuntimeException(
            'detalhe interno'
        );

        $this->assertSame(
            'A foto facial informada não foi encontrada.',
            ExecuteFacialPhotoValidationException::photoNotFound()
                ->getMessage()
        );

        $this->assertSame(
            'O arquivo original da foto facial não está disponível para validação.',
            ExecuteFacialPhotoValidationException::sourceMediaUnavailable()
                ->getMessage()
        );

        $this->assertSame(
            'A foto facial com situação “Aprovada” não pode ser validada novamente.',
            ExecuteFacialPhotoValidationException::statusNotEligible(
                FacialPhotoStatus::Approved
            )->getMessage()
        );

        $failure =
            ExecuteFacialPhotoValidationException::validationFailed(
                $previous
            );

        $this->assertSame(
            'Não foi possível executar a validação facial.',
            $failure->getMessage()
        );

        $this->assertSame(
            $previous,
            $failure->getPrevious()
        );
    }

    private function target(): FacialPhotoValidationTarget
    {
        return new FacialPhotoValidationTarget(
            photoId: 'photo-1',
            status: FacialPhotoStatus::PendingValidation,
            mediaId: 10,
            absolutePath: '/tmp/synthetic-photo.jpg',
            sha256: str_repeat('a', 64),
        );
    }

    private function approvedValidation(): FacialPhotoValidationResult
    {
        return new FacialPhotoValidationResult(
            validator: 'synthetic-validator',
            version: 'synthetic-v1',
            decision: FacialPhotoValidationDecision::Approved,
            faceCount: 1,
            metrics: [
                'confidence' => 0.99,
            ],
            issues: [],
        );
    }

    /**
     * @param  callable(): mixed  $callback
     */
    private function assertInvalid(
        callable $callback,
        string $expectedMessage,
    ): void {
        try {
            $callback();

            $this->fail(
                'Era esperada uma falha de validação do contrato.'
            );
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                $expectedMessage,
                $exception->getMessage()
            );
        }
    }
}
