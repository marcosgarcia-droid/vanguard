<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialPhotos\Preview;

use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoPreviewDecision;
use InvalidArgumentException;

final readonly class FacialPhotoPreviewResult
{
    /**
     * @param  list<FacialPhotoPreviewIssue>  $issues
     */
    public function __construct(
        public FacialPhotoPreviewDecision $decision,
        public ?string $fingerprint,
        public bool $technicalAnalysisPassed,
        public bool $facialValidationPerformed,
        public array $issues,
    ) {
        $this->validateFingerprint();
        $this->validateIssues();
        $this->validateDecision();
    }

    public function canUsePhoto(): bool
    {
        return $this->decision
            === FacialPhotoPreviewDecision::Approved
            && $this->fingerprint !== null;
    }

    /**
     * Retorna somente os dados destinados à apresentação.
     *
     * O fingerprint permanece separado porque será usado apenas
     * para conferir se o arquivo confirmado é o mesmo analisado.
     *
     * @return array{
     *     decision: string,
     *     label: string,
     *     can_use_photo: bool,
     *     technical_analysis_passed: bool,
     *     facial_validation_performed: bool,
     *     issues: list<array{
     *         source: string,
     *         source_label: string,
     *         code: string,
     *         label: string,
     *         guidance: string
     *     }>
     * }
     */
    public function presentation(): array
    {
        return [
            'decision' => $this->decision->value,
            'label' => $this->decision->label(),
            'can_use_photo' => $this->canUsePhoto(),
            'technical_analysis_passed' => $this->technicalAnalysisPassed,
            'facial_validation_performed' => $this->facialValidationPerformed,
            'issues' => array_map(
                static fn (
                    FacialPhotoPreviewIssue $issue
                ): array => $issue->toArray(),
                $this->issues
            ),
        ];
    }

    private function validateFingerprint(): void
    {
        if ($this->fingerprint === null) {
            return;
        }

        if (
            preg_match(
                '/\A[a-f0-9]{64}\z/i',
                $this->fingerprint
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'O fingerprint da foto facial é inválido.'
            );
        }
    }

    private function validateIssues(): void
    {
        if (! array_is_list($this->issues)) {
            throw new InvalidArgumentException(
                'As ocorrências da pré-validação devem formar uma lista.'
            );
        }

        foreach ($this->issues as $issue) {
            if (
                ! $issue
                    instanceof FacialPhotoPreviewIssue
            ) {
                throw new InvalidArgumentException(
                    'A pré-validação contém uma ocorrência inválida.'
                );
            }
        }
    }

    private function validateDecision(): void
    {
        if (
            $this->decision
                === FacialPhotoPreviewDecision::Approved
        ) {
            if (
                ! $this->technicalAnalysisPassed
                || ! $this->facialValidationPerformed
                || $this->fingerprint === null
                || $this->issues !== []
            ) {
                throw new InvalidArgumentException(
                    'Uma foto aprovada exige análises concluídas, '
                    .'fingerprint e ausência de ocorrências.'
                );
            }

            return;
        }

        if ($this->issues === []) {
            throw new InvalidArgumentException(
                'Uma decisão não aprovada deve informar uma ocorrência.'
            );
        }

        if (
            $this->decision
                === FacialPhotoPreviewDecision::Inconclusive
            && (
                ! $this->technicalAnalysisPassed
                || $this->fingerprint === null
            )
        ) {
            throw new InvalidArgumentException(
                'Uma pré-validação inconclusiva exige uma imagem '
                .'tecnicamente válida e identificável.'
            );
        }
    }
}
