<?php

namespace App\Modules\Operations\Domain\FacialPhotos;

use InvalidArgumentException;

final readonly class FacialPhotoValidationResult
{
    /**
     * @param  array<string, bool|int|float|string|null>  $metrics
     * @param  list<FacialPhotoValidationIssue>  $issues
     */
    public function __construct(
        public string $validator,
        public string $version,
        public FacialPhotoValidationDecision $decision,
        public int $faceCount,
        public array $metrics,
        public array $issues,
    ) {
        $this->validateIdentity();
        $this->validateFaceCount();
        $this->validateMetrics();
        $this->validateIssues();
        $this->validateDecisionConsistency();
        $this->validateDetectionConsistency();
    }

    public function isApproved(): bool
    {
        return $this->decision
            === FacialPhotoValidationDecision::Approved;
    }

    public function isRejected(): bool
    {
        return $this->decision
            === FacialPhotoValidationDecision::Rejected;
    }

    public function isInconclusive(): bool
    {
        return $this->decision
            === FacialPhotoValidationDecision::Inconclusive;
    }

    /**
     * @return list<string>
     */
    public function issueCodes(): array
    {
        return array_map(
            static fn (
                FacialPhotoValidationIssue $issue
            ): string => $issue->value,
            $this->issues
        );
    }

    public function hasIssue(
        FacialPhotoValidationIssue $issue
    ): bool {
        return in_array(
            $issue,
            $this->issues,
            true
        );
    }

    /**
     * @return array{
     *     validator: string,
     *     version: string,
     *     decision: string,
     *     decision_label: string,
     *     face_count: int,
     *     metrics: array<string, bool|int|float|string|null>,
     *     issues: list<array{
     *         code: string,
     *         label: string,
     *         guidance: string
     *     }>
     * }
     */
    public function toArray(): array
    {
        return [
            'validator' => $this->validator,
            'version' => $this->version,
            'decision' => $this->decision->value,
            'decision_label' => $this->decision->label(),
            'face_count' => $this->faceCount,
            'metrics' => $this->metrics,
            'issues' => array_map(
                static fn (
                    FacialPhotoValidationIssue $issue
                ): array => [
                    'code' => $issue->value,
                    'label' => $issue->label(),
                    'guidance' => $issue->guidance(),
                ],
                $this->issues
            ),
        ];
    }

    private function validateIdentity(): void
    {
        if (trim($this->validator) === '') {
            throw new InvalidArgumentException(
                'O identificador do validador facial é obrigatório.'
            );
        }

        if (trim($this->version) === '') {
            throw new InvalidArgumentException(
                'A versão da validação facial é obrigatória.'
            );
        }
    }

    private function validateFaceCount(): void
    {
        if ($this->faceCount < 0) {
            throw new InvalidArgumentException(
                'A quantidade de rostos não pode ser negativa.'
            );
        }
    }

    private function validateMetrics(): void
    {
        foreach ($this->metrics as $key => $value) {
            if (
                ! is_string($key)
                || trim($key) === ''
            ) {
                throw new InvalidArgumentException(
                    'Toda métrica facial deve possuir uma chave textual.'
                );
            }

            if (
                $value !== null
                && ! is_scalar($value)
            ) {
                throw new InvalidArgumentException(
                    'As métricas faciais devem conter somente valores escalares.'
                );
            }
        }
    }

    private function validateIssues(): void
    {
        if (! array_is_list($this->issues)) {
            throw new InvalidArgumentException(
                'As ocorrências faciais devem formar uma lista.'
            );
        }

        $registeredIssues = [];

        foreach ($this->issues as $issue) {
            if (
                ! $issue
                    instanceof FacialPhotoValidationIssue
            ) {
                throw new InvalidArgumentException(
                    'A validação contém uma ocorrência facial inválida.'
                );
            }

            if (
                array_key_exists(
                    $issue->value,
                    $registeredIssues
                )
            ) {
                throw new InvalidArgumentException(
                    'A validação não pode conter ocorrências faciais duplicadas.'
                );
            }

            $registeredIssues[$issue->value] = true;
        }
    }

    private function validateDecisionConsistency(): void
    {
        if (
            $this->decision
                === FacialPhotoValidationDecision::Approved
        ) {
            if ($this->faceCount !== 1) {
                throw new InvalidArgumentException(
                    'Uma aprovação facial exige exatamente um rosto.'
                );
            }

            if ($this->issues !== []) {
                throw new InvalidArgumentException(
                    'Uma aprovação facial não pode possuir ocorrências impeditivas.'
                );
            }

            return;
        }

        if ($this->issues === []) {
            throw new InvalidArgumentException(
                'Uma decisão não aprovada deve informar ao menos uma ocorrência.'
            );
        }

        $inconclusiveIssueCount = count(
            array_filter(
                $this->issues,
                static fn (
                    FacialPhotoValidationIssue $issue
                ): bool => $issue->requiresInconclusiveDecision()
            )
        );

        if (
            $this->decision
                === FacialPhotoValidationDecision::Inconclusive
            && $inconclusiveIssueCount
                !== count($this->issues)
        ) {
            throw new InvalidArgumentException(
                'Uma decisão inconclusiva deve conter somente falhas do validador.'
            );
        }

        if (
            $this->decision
                === FacialPhotoValidationDecision::Rejected
            && $inconclusiveIssueCount > 0
        ) {
            throw new InvalidArgumentException(
                'Uma falha do validador não pode reprovar a foto facial.'
            );
        }
    }

    private function validateDetectionConsistency(): void
    {
        if (
            $this->hasIssue(
                FacialPhotoValidationIssue::NoFaceDetected
            )
            && $this->faceCount !== 0
        ) {
            throw new InvalidArgumentException(
                'A ocorrência de rosto não detectado exige contagem igual a zero.'
            );
        }

        if (
            $this->hasIssue(
                FacialPhotoValidationIssue::MultipleFacesDetected
            )
            && $this->faceCount < 2
        ) {
            throw new InvalidArgumentException(
                'A ocorrência de múltiplos rostos exige contagem igual ou superior a dois.'
            );
        }
    }
}
