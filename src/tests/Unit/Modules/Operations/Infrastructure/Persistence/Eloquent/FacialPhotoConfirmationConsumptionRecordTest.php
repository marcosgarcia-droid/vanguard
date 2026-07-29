<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoSource;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoConfirmationConsumptionRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class FacialPhotoConfirmationConsumptionRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_preserves_a_private_confirmation_consumption_ledger(): void
    {
        $context = $this->createContext();

        $consumption = $this->createConsumption(
            $context
        );

        $this->assertTrue(
            $consumption->facialPhoto->is(
                $context['photo']
            )
        );

        $this->assertTrue(
            $consumption->visitor->is(
                $context['visitor']
            )
        );

        $this->assertTrue(
            $consumption->tenant->is(
                $context['tenant']
            )
        );

        $this->assertTrue(
            $consumption->organization->is(
                $context['organization']
            )
        );

        $this->assertTrue(
            $consumption->confirmer->is(
                $context['user']
            )
        );

        $this->assertContains(
            'confirmation_key',
            $consumption->getHidden()
        );

        $this->assertContains(
            'confirmation_context',
            $consumption->getHidden()
        );

        $this->assertContains(
            'photo_sha256',
            $consumption->getHidden()
        );

        $serialized = $consumption->toArray();

        $this->assertArrayNotHasKey(
            'confirmation_key',
            $serialized
        );

        $this->assertArrayNotHasKey(
            'confirmation_context',
            $serialized
        );

        $this->assertArrayNotHasKey(
            'photo_sha256',
            $serialized
        );
    }

    public function test_confirmation_key_is_unique(): void
    {
        $context = $this->createContext();

        $confirmationKey = hash(
            'sha256',
            'opaque-receipt-one'
        );

        $this->createConsumption(
            context: $context,
            overrides: [
                'confirmation_key' => $confirmationKey,
            ],
        );

        $secondPhoto = $this->createPhoto(
            context: $context,
            fingerprint: str_repeat(
                'b',
                64
            ),
        );

        $this->expectException(
            QueryException::class
        );

        $this->createConsumption(
            context: $context,
            overrides: [
                'facial_photo_id' => $secondPhoto->id,

                'confirmation_key' => $confirmationKey,

                'photo_sha256' => str_repeat(
                    'b',
                    64
                ),
            ],
        );
    }

    public function test_each_facial_photo_has_only_one_confirmation_consumption(): void
    {
        $context = $this->createContext();

        $this->createConsumption(
            $context
        );

        $this->expectException(
            QueryException::class
        );

        $this->createConsumption(
            context: $context,
            overrides: [
                'confirmation_key' => hash(
                    'sha256',
                    'opaque-receipt-two'
                ),
            ],
        );
    }

    public function test_it_cannot_be_updated(): void
    {
        $consumption = $this->createConsumption(
            $this->createContext()
        );

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Os consumos de confirmações faciais são imutáveis.'
        );

        $consumption
            ->forceFill([
                'confirmation_context' => 'altered.context',
            ])
            ->save();
    }

    public function test_it_cannot_be_deleted(): void
    {
        $consumption = $this->createConsumption(
            $this->createContext()
        );

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Os consumos de confirmações faciais não podem ser excluídos.'
        );

        $consumption->delete();
    }

    /**
     * @return array{
     *     tenant: TenantRecord,
     *     organization: OrganizationRecord,
     *     visitor: VisitorRecord,
     *     user: User,
     *     photo: FacialPhotoRecord
     * }
     */
    private function createContext(): array
    {
        $tenant = TenantRecord::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'GRUPO CONSUMO FACIAL',
            'status' => 'active',
        ]);

        $organization =
            OrganizationRecord::query()->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'status' => 'active',
                'legal_name' => 'UNIDADE CONSUMO FACIAL LTDA',
                'display_name' => 'UNIDADE CONSUMO FACIAL',
                'unit_code' => 'FCF-01',
            ]);

        $visitor = VisitorRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'full_name' => 'VISITANTE CONSUMO FACIAL',
            'status' => VisitorStatus::Active,
        ]);

        $user = User::factory()->create([
            'name' => 'OPERADOR CONSUMO FACIAL',
        ]);

        $context = [
            'tenant' => $tenant,
            'organization' => $organization,
            'visitor' => $visitor,
            'user' => $user,
        ];

        return [
            ...$context,

            'photo' => $this->createPhoto(
                context: $context,
                fingerprint: str_repeat(
                    'a',
                    64
                ),
            ),
        ];
    }

    /**
     * @param array{
     *     tenant: TenantRecord,
     *     organization: OrganizationRecord,
     *     visitor: VisitorRecord,
     *     user: User
     * } $context
     */
    private function createPhoto(
        array $context,
        string $fingerprint
    ): FacialPhotoRecord {
        return $context['visitor']
            ->facialPhotos()
            ->create([
                'tenant_id' => $context['tenant']->id,

                'organization_id' => $context['organization']->id,

                'created_by' => $context['user']->id,

                'source' => FacialPhotoSource::Webcam,

                'status' => FacialPhotoStatus::PendingValidation,

                'captured_at' => now(),

                'sha256' => $fingerprint,
            ]);
    }

    /**
     * @param array{
     *     tenant: TenantRecord,
     *     organization: OrganizationRecord,
     *     visitor: VisitorRecord,
     *     user: User,
     *     photo: FacialPhotoRecord
     * } $context
     * @param  array<string, mixed>  $overrides
     */
    private function createConsumption(
        array $context,
        array $overrides = []
    ): FacialPhotoConfirmationConsumptionRecord {
        return FacialPhotoConfirmationConsumptionRecord::query()
            ->create([
                ...[
                    'facial_photo_id' => $context['photo']->id,

                    'visitor_id' => $context['visitor']->id,

                    'tenant_id' => $context['tenant']->id,

                    'organization_id' => $context['organization']->id,

                    'confirmed_by' => $context['user']->id,

                    'confirmation_key' => hash(
                        'sha256',
                        'opaque-receipt-default'
                    ),

                    'confirmation_context' => 'visitor.update.'
                        .$context['visitor']->id
                        .'.photo_capture',

                    'photo_sha256' => str_repeat(
                        'a',
                        64
                    ),

                    'consumed_at' => now(),
                ],

                ...$overrides,
            ]);
    }
}
