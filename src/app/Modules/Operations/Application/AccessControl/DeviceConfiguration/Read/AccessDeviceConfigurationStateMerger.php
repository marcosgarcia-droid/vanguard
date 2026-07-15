<?php

namespace App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read;

final class AccessDeviceConfigurationStateMerger
{
    /**
     * Mescla um estado parcial observado sobre o último estado conhecido.
     *
     * Arrays associativos são combinados recursivamente.
     * Listas numéricas e valores escalares são substituídos.
     *
     * @param  array<string|int, mixed>  $current
     * @param  array<string|int, mixed>  $observed
     * @return array<string|int, mixed>
     */
    public function merge(
        array $current,
        array $observed
    ): array {
        $merged = $current;

        foreach ($observed as $key => $observedValue) {
            $currentValue =
                $merged[$key] ?? null;

            if (
                is_array($currentValue)
                && is_array($observedValue)
                && ! array_is_list($currentValue)
                && ! array_is_list($observedValue)
            ) {
                $merged[$key] = $this->merge(
                    $currentValue,
                    $observedValue
                );

                continue;
            }

            $merged[$key] = $observedValue;
        }

        return $merged;
    }
}
