<?php

namespace Tests\Unit\Modules\Operations\Infrastructure\Integrations\Intelbras;

use App\Modules\Operations\Infrastructure\Integrations\Intelbras\IntelbrasConfigurationResponseSanitizer;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\IntelbrasReadOnlyEndpoint;
use PHPUnit\Framework\TestCase;

class IntelbrasConfigurationResponseSanitizerTest extends TestCase
{
    public function test_it_keeps_only_the_explicit_fields_for_current_time(): void
    {
        $sanitized =
            (new IntelbrasConfigurationResponseSanitizer)
                ->sanitize(
                    IntelbrasReadOnlyEndpoint::CurrentTime,
                    [
                        'result' => '2026-07-15 08:30:00',
                        'username' => 'admin',
                        'password' => 'never-persist-this',
                        'FaceData' => 'synthetic-face-content',
                        'Image' => 'synthetic-image-content',
                        'Template' => 'synthetic-template-content',
                        'CPF' => '00000000000',
                    ]
                );

        $this->assertSame(
            [
                'result' => '2026-07-15 08:30:00',
            ],
            $sanitized
        );
    }

    public function test_it_keeps_access_control_fields_in_a_deterministic_order(): void
    {
        $sanitized =
            (new IntelbrasConfigurationResponseSanitizer)
                ->sanitize(
                    IntelbrasReadOnlyEndpoint::AccessControl,
                    [
                        'table.Users[0].Name' => 'Synthetic Person',
                        'table.AccessControl[0].Method' => 35,
                        'table.AccessControl[0].BreakInAlarmEnable' => true,
                        'table.AccessControl[0].UnlockHoldInterval' => 3000,
                        'table.AccessControl[0].CloseTimeout' => 10,
                        'table.AccessControl[0].SensorEnable' => true,
                        'table.AccessControl[0].DuressAlarmEnable' => false,
                        'table.AccessControl[0].DoorNotClosedAlarmEnable' => true,
                        'FaceTemplate' => 'synthetic-biometric-template',
                    ]
                );

        $this->assertSame(
            [
                'table.AccessControl[0].BreakInAlarmEnable' => true,
                'table.AccessControl[0].DoorNotClosedAlarmEnable' => true,
                'table.AccessControl[0].DuressAlarmEnable' => false,
                'table.AccessControl[0].SensorEnable' => true,
                'table.AccessControl[0].CloseTimeout' => 10,
                'table.AccessControl[0].Method' => 35,
                'table.AccessControl[0].UnlockHoldInterval' => 3000,
            ],
            $sanitized
        );
    }

    public function test_it_rejects_non_scalar_and_oversized_values(): void
    {
        $sanitizer =
            new IntelbrasConfigurationResponseSanitizer;

        $this->assertSame(
            [],
            $sanitizer->sanitize(
                IntelbrasReadOnlyEndpoint::CurrentTime,
                [
                    'result' => [
                        'unexpected-array',
                    ],
                ]
            )
        );

        $this->assertSame(
            [],
            $sanitizer->sanitize(
                IntelbrasReadOnlyEndpoint::SoftwareVersion,
                [
                    'version' => str_repeat(
                        'A',
                        1025
                    ),
                ]
            )
        );
    }

    public function test_it_removes_control_characters_from_allowed_strings(): void
    {
        $sanitized =
            (new IntelbrasConfigurationResponseSanitizer)
                ->sanitize(
                    IntelbrasReadOnlyEndpoint::DoorStatus,
                    [
                        'Info.status' => "Cl\x00o\x07se",
                    ]
                );

        $this->assertSame(
            [
                'Info.status' => 'Close',
            ],
            $sanitized
        );
    }
}
