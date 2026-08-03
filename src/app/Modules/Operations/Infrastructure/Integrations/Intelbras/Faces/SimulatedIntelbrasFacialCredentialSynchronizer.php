<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces;

final class SimulatedIntelbrasFacialCredentialSynchronizer implements IntelbrasFacialCredentialSynchronizer
{
    private readonly IntelbrasFacialCredentialResponseInterpreter $interpreter;

    public function __construct(
        private readonly SimulatedIntelbrasFacialCredentialSynchronizationScenario $scenario,
        ?IntelbrasFacialCredentialResponseInterpreter $interpreter = null,
    ) {
        $this->interpreter = $interpreter
            ?? new IntelbrasFacialCredentialResponseInterpreter;
    }

    public function synchronize(
        IntelbrasFacialCredentialPlan $plan
    ): IntelbrasFacialCredentialSynchronizationResult {
        $response = $this->scenario->interpretUsing(
            $this->interpreter
        );

        return IntelbrasFacialCredentialSynchronizationResult::simulated(
            plan: $plan,
            response: $response,
        );
    }
}
