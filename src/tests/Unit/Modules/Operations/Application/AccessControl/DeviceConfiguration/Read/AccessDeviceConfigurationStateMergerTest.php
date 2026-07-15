<?php

namespace Tests\Unit\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read;

use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read\AccessDeviceConfigurationStateMerger;
use PHPUnit\Framework\TestCase;

class AccessDeviceConfigurationStateMergerTest extends TestCase
{
    public function test_it_preserves_unobserved_fields_and_replaces_observed_values(): void
    {
        $current = [
            'device' => [
                'date_time' => '2026-07-14 16:00:00',
                'firmware_version' => 'FIRMWARE-ANTERIOR',
            ],
            'door' => [
                'current_status' => 'Open',
                'relay_activation_seconds' => 3,
            ],
            'alarms' => [
                'door_open_enabled' => true,
            ],
        ];

        $observed = [
            'device' => [
                'date_time' => '2026-07-15 08:30:00',
            ],
            'door' => [
                'current_status' => 'Close',
            ],
        ];

        $merged =
            (new AccessDeviceConfigurationStateMerger)
                ->merge(
                    $current,
                    $observed
                );

        $this->assertSame(
            [
                'device' => [
                    'date_time' => '2026-07-15 08:30:00',
                    'firmware_version' => 'FIRMWARE-ANTERIOR',
                ],
                'door' => [
                    'current_status' => 'Close',
                    'relay_activation_seconds' => 3,
                ],
                'alarms' => [
                    'door_open_enabled' => true,
                ],
            ],
            $merged
        );
    }

    public function test_it_replaces_numeric_lists_instead_of_concatenating_them(): void
    {
        $merged =
            (new AccessDeviceConfigurationStateMerger)
                ->merge(
                    [
                        'channels' => [
                            'entrada-01',
                            'entrada-02',
                        ],
                    ],
                    [
                        'channels' => [
                            'saida-01',
                        ],
                    ]
                );

        $this->assertSame(
            [
                'channels' => [
                    'saida-01',
                ],
            ],
            $merged
        );
    }

    public function test_it_accepts_a_partial_state_when_no_previous_state_exists(): void
    {
        $observed = [
            'door' => [
                'current_status' => 'Close',
            ],
        ];

        $this->assertSame(
            $observed,
            (new AccessDeviceConfigurationStateMerger)
                ->merge([], $observed)
        );
    }
}
