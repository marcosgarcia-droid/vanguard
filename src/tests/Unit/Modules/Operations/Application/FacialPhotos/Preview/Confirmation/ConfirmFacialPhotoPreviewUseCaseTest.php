<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Application\FacialPhotos\Preview\Confirmation;

use App\Modules\Operations\Application\FacialPhotos\Preview\Confirmation\ConfirmFacialPhotoPreviewCommand;
use App\Modules\Operations\Application\FacialPhotos\Preview\Confirmation\ConfirmFacialPhotoPreviewException;
use App\Modules\Operations\Application\FacialPhotos\Preview\Confirmation\ConfirmFacialPhotoPreviewUseCase;
use App\Modules\Operations\Application\FacialPhotos\Preview\PreviewFacialPhotoUseCase;
use App\Modules\Operations\Application\FacialPhotos\Preview\Receipts\FacialPhotoPreviewReceipt;
use App\Modules\Operations\Application\FacialPhotos\Preview\Receipts\FacialPhotoPreviewReceiptCodec;
use App\Modules\Operations\Application\FacialPhotos\Preview\Receipts\FacialPhotoPreviewReceiptException;
use App\Modules\Operations\Application\FacialPhotos\TechnicalAnalysis\AnalyzeFacialPhotoUseCase;
use App\Modules\Operations\Application\FacialPhotos\TechnicalAnalysis\FacialPhotoTechnicalAnalyzer;
use App\Modules\Operations\Application\FacialPhotos\Validation\FacialPhotoValidator;
use App\Modules\Operations\Application\FacialPhotos\Validation\Resolution\FacialPhotoValidatorResolver;
use App\Modules\Operations\Application\FacialPhotos\Validation\Resolution\FacialPhotoValidatorSelection;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoPreviewDecision;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoTechnicalAnalysis;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoTechnicalIssue;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationDecision;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationIssue;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationResult;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ConfirmFacialPhotoPreviewUseCaseTest extends TestCase
{
    private const FINGERPRINT =
        'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'
        .'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private const CHANGED_FINGERPRINT =
        'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'
        .'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    private const STATE_PATH =
        'mountedActions.0.data.photo_capture';

    private const USER_ID = 10;

    private string $absolutePath;

    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();

        $temporaryPath =
            tempnam(
                sys_get_temp_dir(),
                'vanguard-confirm-facial-photo-'
            );

        if (! is_string($temporaryPath)) {
            $this->fail(
                'Não foi possível criar o arquivo temporário.'
            );
        }

        file_put_contents(
            $temporaryPath,
            'synthetic-facial-photo'
        );

        $this->absolutePath =
            $temporaryPath;

        $this->now =
            new DateTimeImmutable(
                '2026-07-28T16:00:00-03:00'
            );
    }

    protected function tearDown(): void
    {
        if (isset($this->absolutePath)) {
            @unlink(
                $this->absolutePath
            );
        }

        parent::tearDown();
    }

    public function test_it_confirms_the_same_usable_photo(): void
    {
        $result = $this->useCase(
            receipt: $this->receipt(),
            analysis: $this->passedAnalysis(),
        )->execute(
            $this->command()
        );

        $this->assertSame(
            self::FINGERPRINT,
            $result->fingerprint
        );

        $this->assertSame(
            hash(
                'sha256',
                'opaque-receipt'
            ),
            $result->confirmationKey
        );

        $this->assertSame(
            self::STATE_PATH,
            $result->confirmationContext
        );

        $this->assertSame(
            FacialPhotoPreviewDecision::Approved,
            $result->decision
        );

        $this->assertFalse(
            $result->awaitsAdditionalValidation()
        );
    }

    public function test_it_rejects_when_the_current_result_becomes_inconclusive(): void
    {
        $this->expectException(
            ConfirmFacialPhotoPreviewException::class
        );

        $this->expectExceptionMessage(
            'A foto não atende mais aos requisitos para uso. '
                .'Corrija ou escolha outra imagem.'
        );

        $this->useCase(
            receipt: $this->receipt(),
            analysis: $this->passedAnalysis(),
            validationEnabled: false,
        )->execute(
            $this->command()
        );
    }

    public function test_it_rejects_an_invalid_encoded_receipt(): void
    {
        $this->expectException(
            ConfirmFacialPhotoPreviewException::class
        );

        $this->expectExceptionMessage(
            'A confirmação temporária da foto é inválida. '
                .'Analise a imagem novamente.'
        );

        $this->useCase(
            receipt: $this->receipt(),
            analysis: $this->passedAnalysis(),
            codecFails: true,
        )->execute(
            $this->command()
        );
    }

    public function test_it_rejects_an_expired_receipt_at_the_boundary(): void
    {
        $this->expectException(
            ConfirmFacialPhotoPreviewException::class
        );

        $this->expectExceptionMessage(
            'A análise temporária da foto expirou. '
                .'Analise a imagem novamente.'
        );

        $this->useCase(
            receipt: $this->receipt(
                expiresAt: $this->now
            ),
            analysis: $this->passedAnalysis(),
        )->execute(
            $this->command()
        );
    }

    public function test_it_rejects_a_receipt_from_another_state_path(): void
    {
        $this->expectException(
            ConfirmFacialPhotoPreviewException::class
        );

        $this->expectExceptionMessage(
            'A confirmação da foto não corresponde a este envio. '
                .'Analise a imagem novamente.'
        );

        $this->useCase(
            receipt: $this->receipt(),
            analysis: $this->passedAnalysis(),
        )->execute(
            $this->command(
                expectedStatePath: 'mountedActions.1.data.photo_capture'
            )
        );
    }

    public function test_it_rejects_a_receipt_from_another_user(): void
    {
        $this->expectException(
            ConfirmFacialPhotoPreviewException::class
        );

        $this->expectExceptionMessage(
            'A confirmação da foto não corresponde a este envio. '
                .'Analise a imagem novamente.'
        );

        $this->useCase(
            receipt: $this->receipt(),
            analysis: $this->passedAnalysis(),
        )->execute(
            $this->command(
                userId: 11
            )
        );
    }

    public function test_it_rejects_an_unavailable_temporary_upload(): void
    {
        $this->expectException(
            ConfirmFacialPhotoPreviewException::class
        );

        $this->expectExceptionMessage(
            'A foto selecionada não está mais disponível. '
                .'Escolha a imagem novamente.'
        );

        $this->useCase(
            receipt: $this->receipt(),
            analysis: $this->passedAnalysis(),
        )->execute(
            $this->command(
                absolutePath: $this->absolutePath.'-missing'
            )
        );
    }

    public function test_it_rejects_a_photo_changed_after_preview(): void
    {
        $this->expectException(
            ConfirmFacialPhotoPreviewException::class
        );

        $this->expectExceptionMessage(
            'A foto foi alterada depois da análise. '
                .'Analise a imagem novamente.'
        );

        $this->useCase(
            receipt: $this->receipt(),
            analysis: $this->passedAnalysis(
                self::CHANGED_FINGERPRINT
            ),
        )->execute(
            $this->command()
        );
    }

    public function test_it_rejects_a_photo_that_now_fails_technical_analysis(): void
    {
        $this->expectException(
            ConfirmFacialPhotoPreviewException::class
        );

        $this->expectExceptionMessage(
            'A foto não atende mais aos requisitos para uso. '
                .'Corrija ou escolha outra imagem.'
        );

        $this->useCase(
            receipt: $this->receipt(),
            analysis: new FacialPhotoTechnicalAnalysis(
                version: 'technical-test-v1',
                passed: false,
                metrics: [
                    'sha256' => self::FINGERPRINT,
                ],
                issues: [
                    FacialPhotoTechnicalIssue::Underexposed,
                ],
            ),
        )->execute(
            $this->command()
        );
    }

    public function test_it_rejects_a_photo_that_now_fails_facial_validation(): void
    {
        $this->expectException(
            ConfirmFacialPhotoPreviewException::class
        );

        $this->expectExceptionMessage(
            'A foto não atende mais aos requisitos para uso. '
                .'Corrija ou escolha outra imagem.'
        );

        $this->useCase(
            receipt: $this->receipt(),
            analysis: $this->passedAnalysis(),
            validationDecision: FacialPhotoValidationDecision::Rejected,
        )->execute(
            $this->command()
        );
    }

    private function command(
        ?string $absolutePath = null,
        string $expectedStatePath = self::STATE_PATH,
        ?int $userId = self::USER_ID,
    ): ConfirmFacialPhotoPreviewCommand {
        return new ConfirmFacialPhotoPreviewCommand(
            encodedReceipt: 'opaque-receipt',
            absolutePath: $absolutePath
                ?? $this->absolutePath,
            expectedStatePath: $expectedStatePath,
            userId: $userId,
            confirmedAt: $this->now,
        );
    }

    private function receipt(
        ?DateTimeImmutable $expiresAt = null
    ): FacialPhotoPreviewReceipt {
        return new FacialPhotoPreviewReceipt(
            fingerprint: self::FINGERPRINT,
            decision: FacialPhotoPreviewDecision::Approved,
            statePath: self::STATE_PATH,
            userId: self::USER_ID,
            expiresAt: $expiresAt
                ?? $this->now->modify('+1 minute'),
        );
    }

    private function passedAnalysis(
        string $fingerprint = self::FINGERPRINT
    ): FacialPhotoTechnicalAnalysis {
        return new FacialPhotoTechnicalAnalysis(
            version: 'technical-test-v1',
            passed: true,
            metrics: [
                'sha256' => $fingerprint,
                'width' => 720,
                'height' => 900,
            ],
            issues: [],
        );
    }

    private function useCase(
        FacialPhotoPreviewReceipt $receipt,
        FacialPhotoTechnicalAnalysis $analysis,
        FacialPhotoValidationDecision $validationDecision =
            FacialPhotoValidationDecision::Approved,
        bool $validationEnabled = true,
        bool $codecFails = false,
    ): ConfirmFacialPhotoPreviewUseCase {
        $codec =
            new class($receipt, $codecFails) implements FacialPhotoPreviewReceiptCodec
            {
                public function __construct(
                    private FacialPhotoPreviewReceipt $receipt,
                    private bool $fails,
                ) {}

                public function encode(
                    FacialPhotoPreviewReceipt $receipt
                ): string {
                    return 'opaque-receipt';
                }

                public function decode(
                    string $encodedReceipt
                ): FacialPhotoPreviewReceipt {
                    if ($this->fails) {
                        throw FacialPhotoPreviewReceiptException::invalid();
                    }

                    return $this->receipt;
                }
            };

        $analyzer =
            new class($analysis) implements FacialPhotoTechnicalAnalyzer
            {
                public function __construct(
                    private FacialPhotoTechnicalAnalysis $analysis
                ) {}

                public function analyze(
                    string $absolutePath
                ): FacialPhotoTechnicalAnalysis {
                    return $this->analysis;
                }
            };

        $resolver =
            new class($validationDecision) implements FacialPhotoValidatorResolver
            {
                public function __construct(
                    private FacialPhotoValidationDecision $decision
                ) {}

                public function resolve(
                    FacialPhotoValidatorSelection $selection
                ): FacialPhotoValidator {
                    return new class($this->decision) implements FacialPhotoValidator
                    {
                        public function __construct(
                            private FacialPhotoValidationDecision $decision
                        ) {}

                        public function validate(
                            string $absolutePath
                        ): FacialPhotoValidationResult {
                            return new FacialPhotoValidationResult(
                                validator: 'facial-test',
                                version: 'facial-test-v1',
                                decision: $this->decision,
                                faceCount: 1,
                                metrics: [],
                                issues: $this->decision
                                    === FacialPhotoValidationDecision::Rejected
                                        ? [
                                            FacialPhotoValidationIssue::FaceNotCentered,
                                        ]
                                        : [],
                            );
                        }
                    };
                }
            };

        $preview =
            new PreviewFacialPhotoUseCase(
                technicalAnalysis: new AnalyzeFacialPhotoUseCase(
                    $analyzer
                ),
                validatorResolver: $resolver,
                facialValidationEnabled: $validationEnabled,
                provider: $validationEnabled
                    ? 'simulator'
                    : null,
                scenario: $validationEnabled
                    ? 'approved'
                    : null,
            );

        return new ConfirmFacialPhotoPreviewUseCase(
            receiptCodec: $codec,
            previewFacialPhoto: $preview,
        );
    }
}
