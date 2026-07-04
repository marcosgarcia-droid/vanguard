<?php

namespace App\Modules\Identity\Infrastructure\Persistence\Eloquent;

use App\Modules\Identity\Application\Organizations\RegistrationData\OrganizationRegistrationDataRepository;
use App\Modules\Identity\Domain\Organizations\ValueObjects\Cnpj;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use JsonException;

final class EloquentOrganizationRegistrationDataRepository implements OrganizationRegistrationDataRepository
{
    private const SOURCE = 'cnpj_lookup';

    public function applyFromCnpjLookup(
        string $organizationId,
        Cnpj $cnpj,
        string $provider,
        array $normalizedPayload,
    ): void {
        $now = now();

        $this->updateOrganization($organizationId, $cnpj, $provider, $normalizedPayload, $now);
        $this->replaceAddress($organizationId, $normalizedPayload, $now);
        $this->replaceContacts($organizationId, $normalizedPayload, $now);
        $this->replaceCnaeActivities($organizationId, $normalizedPayload, $now);
        $this->replaceMembers($organizationId, $normalizedPayload, $now);
        $this->replaceTaxRegime($organizationId, $normalizedPayload, $now);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function updateOrganization(
        string $organizationId,
        Cnpj $cnpj,
        string $provider,
        array $payload,
        DateTimeInterface $now,
    ): void {
        DB::table('organizations')
            ->where('id', $organizationId)
            ->update($this->withoutNulls([
                'cnpj' => $cnpj->value(),
                'cnpj_formatted' => $cnpj->formatted(),
                'cnpj_root' => $cnpj->root(),
                'cnpj_branch' => $cnpj->branch(),
                'cnpj_check_digits' => $cnpj->checkDigits(),
                'legal_name' => $this->firstString($payload, ['legal_name']),
                'trade_name' => $this->firstString($payload, ['trade_name']),
                'establishment_type' => $this->firstString($payload, ['establishment_type']),
                'is_head_office' => $this->firstBoolean($payload, ['is_head_office']),
                'opened_at' => $this->firstDate($payload, ['opened_at', 'opening_date']),
                'closed_at' => $this->firstDate($payload, ['closed_at', 'closing_date']),
                'legal_nature_code' => $this->firstString($payload, ['legal_nature_code']),
                'legal_nature_name' => $this->firstString($payload, ['legal_nature_name']),
                'company_size_code' => $this->firstString($payload, ['company_size_code']),
                'company_size_name' => $this->firstString($payload, ['company_size_name']),
                'share_capital' => $this->firstDecimal($payload, ['share_capital']),
                'tax_registration_status_code' => $this->firstString($payload, ['registration_status_code', 'tax_registration_status_code']),
                'tax_registration_status_name' => $this->firstString($payload, ['registration_status_name', 'tax_registration_status_name']),
                'tax_registration_status_date' => $this->firstDate($payload, ['registration_status_date', 'tax_registration_status_date']),
                'tax_registration_status_reason' => $this->firstString($payload, ['registration_status_reason', 'tax_registration_status_reason']),
                'special_status' => $this->firstString($payload, ['special_status']),
                'special_status_date' => $this->firstDate($payload, ['special_status_date']),
                'responsible_federative_entity' => $this->firstString($payload, ['responsible_federative_entity']),
                'cnpj_synced_at' => $now,
                'cnpj_sync_provider' => $provider,
                'cnpj_normalized_data' => $this->json($payload),
                'updated_at' => $now,
            ]));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function replaceAddress(string $organizationId, array $payload, DateTimeInterface $now): void
    {
        $address = $this->arrayValue($payload, 'address');

        DB::table('organization_addresses')
            ->where('organization_id', $organizationId)
            ->where('source', self::SOURCE)
            ->delete();

        if ($address === []) {
            return;
        }

        DB::table('organization_addresses')->insert($this->withoutNulls([
            'organization_id' => $organizationId,
            'type' => 'main',
            'label' => 'Endereço principal',
            'postal_code' => $this->firstString($address, ['postal_code', 'zip_code', 'cep']),
            'street' => $this->firstString($address, ['street', 'logradouro']),
            'number' => $this->firstString($address, ['number', 'numero']),
            'complement' => $this->firstString($address, ['complement', 'complemento']),
            'district' => $this->firstString($address, ['district', 'neighborhood', 'bairro']),
            'city' => $this->firstString($address, ['city', 'municipio']),
            'city_code' => $this->firstString($address, ['city_code', 'municipality_code']),
            'state' => $this->state($this->firstString($address, ['state', 'uf'])),
            'country_code' => $this->firstString($address, ['country_code']) ?? 'BR',
            'latitude' => $this->firstDecimal($address, ['latitude']),
            'longitude' => $this->firstDecimal($address, ['longitude']),
            'is_primary' => true,
            'source' => self::SOURCE,
            'created_at' => $now,
            'updated_at' => $now,
        ]));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function replaceContacts(string $organizationId, array $payload, DateTimeInterface $now): void
    {
        DB::table('organization_contacts')
            ->where('organization_id', $organizationId)
            ->where('source', self::SOURCE)
            ->delete();

        foreach ($this->contactRows($payload) as $index => $contact) {
            $type = $this->firstString($contact, ['type']) ?? 'other';
            $value = $this->firstString($contact, ['value']);

            if ($value === null) {
                continue;
            }

            DB::table('organization_contacts')->insert($this->withoutNulls([
                'organization_id' => $organizationId,
                'type' => $type,
                'label' => $this->firstString($contact, ['label']),
                'value' => $value,
                'normalized_value' => $this->firstString($contact, ['normalized_value']) ?? $this->normalizeContactValue($type, $value),
                'is_primary' => $this->firstBoolean($contact, ['is_primary']) ?? $index === 0,
                'is_verified' => false,
                'source' => self::SOURCE,
                'notes' => $this->firstString($contact, ['notes']),
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function replaceCnaeActivities(string $organizationId, array $payload, DateTimeInterface $now): void
    {
        DB::table('organization_cnae_activities')
            ->where('organization_id', $organizationId)
            ->where('source', self::SOURCE)
            ->delete();

        $activities = $this->arrayValue($payload, 'cnae');

        if ($activities === []) {
            $activities = $this->arrayValue($payload, 'activities');
        }

        if (! array_is_list($activities)) {
            return;
        }

        foreach ($activities as $index => $activity) {
            if (! is_array($activity)) {
                continue;
            }

            $code = $this->firstString($activity, ['code', 'codigo']);
            $description = $this->firstString($activity, ['description', 'text', 'name']);

            if ($code === null || $description === null) {
                continue;
            }

            DB::table('organization_cnae_activities')->insert([
                'organization_id' => $organizationId,
                'code' => $code,
                'description' => $description,
                'is_primary' => $this->firstBoolean($activity, ['is_primary']) ?? $index === 0,
                'source' => self::SOURCE,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function replaceMembers(string $organizationId, array $payload, DateTimeInterface $now): void
    {
        DB::table('organization_members')
            ->where('organization_id', $organizationId)
            ->where('source', self::SOURCE)
            ->delete();

        $members = $this->arrayValue($payload, 'members');

        if ($members === []) {
            $members = $this->arrayValue($payload, 'qsa');
        }

        if (! array_is_list($members)) {
            return;
        }

        foreach ($members as $member) {
            if (! is_array($member)) {
                continue;
            }

            $name = $this->firstString($member, ['name', 'nome']);

            if ($name === null) {
                continue;
            }

            DB::table('organization_members')->insert($this->withoutNulls([
                'organization_id' => $organizationId,
                'name' => $name,
                'document_type' => $this->firstString($member, ['document_type']),
                'document_number' => $this->firstString($member, ['document_number']),
                'member_type' => $this->firstString($member, ['member_type', 'type']),
                'qualification_code' => $this->firstString($member, ['qualification_code']),
                'qualification_name' => $this->firstString($member, ['qualification_name', 'qualification']),
                'role' => $this->firstString($member, ['role']),
                'is_legal_representative' => $this->firstBoolean($member, ['is_legal_representative']) ?? false,
                'joined_at' => $this->firstDate($member, ['joined_at']),
                'age_range' => $this->firstString($member, ['age_range']),
                'country_code' => $this->firstString($member, ['country_code']),
                'representative_name' => $this->firstString($member, ['representative_name']),
                'representative_document_type' => $this->firstString($member, ['representative_document_type']),
                'representative_document_number' => $this->firstString($member, ['representative_document_number']),
                'source' => self::SOURCE,
                'metadata' => $this->json($member),
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function replaceTaxRegime(string $organizationId, array $payload, DateTimeInterface $now): void
    {
        DB::table('organization_tax_regimes')
            ->where('organization_id', $organizationId)
            ->where('source', self::SOURCE)
            ->delete();

        $taxRegime = $this->arrayValue($payload, 'tax_regime');

        if ($taxRegime === []) {
            return;
        }

        DB::table('organization_tax_regimes')->insert($this->withoutNulls([
            'organization_id' => $organizationId,
            'is_current' => true,
            'is_simples_nacional' => $this->firstBoolean($taxRegime, ['is_simples_nacional']),
            'simples_nacional_opted_at' => $this->firstDate($taxRegime, ['simples_nacional_opted_at']),
            'simples_nacional_excluded_at' => $this->firstDate($taxRegime, ['simples_nacional_excluded_at']),
            'is_mei' => $this->firstBoolean($taxRegime, ['is_mei']),
            'mei_opted_at' => $this->firstDate($taxRegime, ['mei_opted_at']),
            'mei_excluded_at' => $this->firstDate($taxRegime, ['mei_excluded_at']),
            'tax_regime' => $this->firstString($taxRegime, ['tax_regime']),
            'tax_regime_details' => $this->json($taxRegime),
            'effective_from' => $this->firstDate($taxRegime, ['effective_from']),
            'effective_until' => $this->firstDate($taxRegime, ['effective_until']),
            'synced_at' => $now,
            'source' => self::SOURCE,
            'created_at' => $now,
            'updated_at' => $now,
        ]));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function contactRows(array $payload): array
    {
        $contacts = $this->arrayValue($payload, 'contacts');

        if ($contacts !== [] && array_is_list($contacts)) {
            return array_values(array_filter($contacts, is_array(...)));
        }

        $rows = [];

        foreach (['email', 'phone', 'telephone'] as $key) {
            $value = $this->firstString($contacts, [$key]) ?? $this->firstString($payload, [$key]);

            if ($value === null) {
                continue;
            }

            $rows[] = [
                'type' => $key === 'email' ? 'email' : 'phone',
                'value' => $value,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $keys
     */
    private function firstString(array $payload, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $payload) || ! is_scalar($payload[$key])) {
                continue;
            }

            $value = trim((string) $payload[$key]);

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $keys
     */
    private function firstBoolean(array $payload, array $keys): ?bool
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $payload)) {
                continue;
            }

            $value = $payload[$key];

            if (is_bool($value)) {
                return $value;
            }

            if (is_int($value)) {
                return $value === 1;
            }

            if (! is_string($value)) {
                continue;
            }

            $normalized = mb_strtolower(trim($value));

            if (in_array($normalized, ['1', 'true', 'sim', 's', 'yes'], true)) {
                return true;
            }

            if (in_array($normalized, ['0', 'false', 'não', 'nao', 'n', 'no'], true)) {
                return false;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $keys
     */
    private function firstDate(array $payload, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $this->firstString($payload, [$key]);

            if ($value === null) {
                continue;
            }

            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value) === 1) {
                $date = DateTimeImmutable::createFromFormat('d/m/Y', $value);

                return $date instanceof DateTimeImmutable ? $date->format('Y-m-d') : null;
            }

            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value) === 1) {
                return substr($value, 0, 10);
            }

            try {
                return (new DateTimeImmutable($value))->format('Y-m-d');
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $keys
     */
    private function firstDecimal(array $payload, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $this->firstString($payload, [$key]);

            if ($value === null) {
                continue;
            }

            if (str_contains($value, ',')) {
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            }

            if (is_numeric($value)) {
                return number_format((float) $value, 2, '.', '');
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function arrayValue(array $payload, string $key): array
    {
        return isset($payload[$key]) && is_array($payload[$key])
            ? $payload[$key]
            : [];
    }

    private function normalizeContactValue(string $type, string $value): string
    {
        if ($type === 'email') {
            return mb_strtolower($value);
        }

        if ($type === 'phone' || $type === 'telephone') {
            return preg_replace('/\D+/', '', $value) ?? $value;
        }

        return $value;
    }

    private function state(?string $value): ?string
    {
        return $value === null ? null : mb_strtoupper(substr($value, 0, 2));
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function withoutNulls(array $values): array
    {
        return array_filter(
            $values,
            fn (mixed $value): bool => $value !== null,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function json(array $payload): string
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR);

        if (! is_string($json)) {
            throw new JsonException('Unable to encode organization registration data.');
        }

        return $json;
    }
}
