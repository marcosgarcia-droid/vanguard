<?php

namespace App\Modules\Operations\Domain\FacialPhotos;

final readonly class FacialPhotoTechnicalAnalysis
{
    /**
     * @param  array<string, int|float|string|null>  $metrics
     * @param  list<FacialPhotoTechnicalIssue>  $issues
     */
    public function __construct(
        public string $version,
        public bool $passed,
        public array $metrics,
        public array $issues,
    ) {}

    /**
     * @return list<string>
     */
    public function issueCodes(): array
    {
        return array_map(
            static fn (
                FacialPhotoTechnicalIssue $issue
            ): string => $issue->value,
            $this->issues
        );
    }

    /**
     * @return array{
     *     version: string,
     *     passed: bool,
     *     metrics: array<string, int|float|string|null>,
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
            'version' => $this->version,
            'passed' => $this->passed,
            'metrics' => $this->metrics,
            'issues' => array_map(
                static fn (
                    FacialPhotoTechnicalIssue $issue
                ): array => [
                    'code' => $issue->value,
                    'label' => $issue->label(),
                    'guidance' => $issue->guidance(),
                ],
                $this->issues
            ),
        ];
    }
}
