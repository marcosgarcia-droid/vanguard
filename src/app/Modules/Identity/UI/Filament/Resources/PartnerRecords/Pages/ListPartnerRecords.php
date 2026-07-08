<?php

namespace App\Modules\Identity\UI\Filament\Resources\PartnerRecords\Pages;

use App\Models\User;
use App\Modules\Identity\Application\Tenancy\TenantContext;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\PartnerRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Identity\UI\Filament\Resources\PartnerRecords\PartnerRecordResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\DB;

class ListPartnerRecords extends ListRecords
{
    private const GLOBAL_TENANT_OPTION = '__global__';

    protected static string $resource = PartnerRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('changeCurrentTenant')
                ->label(fn (): string => 'Grupo: '.self::currentTenantLabel())
                ->icon('heroicon-o-building-office-2')
                ->color('gray')
                ->visible(fn (): bool => self::canShowTenantSelector())
                ->modalHeading('Trocar grupo ativo')
                ->modalSubmitActionLabel('Aplicar')
                ->form([
                    Select::make('tenant_id')
                        ->label('Grupo empresarial')
                        ->options(fn (): array => self::tenantOptions())
                        ->default(fn (): ?string => self::currentTenantOption())
                        ->required()
                        ->native(false)
                        ->searchable(),
                ])
                ->action(function (array $data) {
                    $tenantId = (string) ($data['tenant_id'] ?? '');
                    $user = auth()->user();
                    $context = app(TenantContext::class);

                    if ($tenantId === self::GLOBAL_TENANT_OPTION) {
                        if (! $user instanceof User || ! $user->hasRole(config('filament-shield.super_admin.name', 'super_admin'))) {
                            Notification::make()
                                ->title('Troca de grupo não permitida')
                                ->body('Você não tem permissão para ativar a visão global.')
                                ->danger()
                                ->send();

                            return redirect(PartnerRecordResource::getUrl());
                        }

                        $context->clearSelectedTenant();

                        Notification::make()
                            ->title('Visão global ativada')
                            ->body('Você voltou a visualizar todos os grupos.')
                            ->success()
                            ->send();

                        return redirect(PartnerRecordResource::getUrl());
                    }

                    $tenant = TenantRecord::query()->find($tenantId);

                    if (! $tenant instanceof TenantRecord || ! $context->selectTenantForUser($user, $tenant)) {
                        Notification::make()
                            ->title('Grupo não selecionado')
                            ->body('O grupo informado está inativo ou não está disponível para o seu usuário.')
                            ->danger()
                            ->send();

                        return redirect(PartnerRecordResource::getUrl());
                    }

                    Notification::make()
                        ->title('Grupo ativo definido')
                        ->body('Agora você está operando no grupo '.$tenant->name.'.')
                        ->success()
                        ->send();

                    return redirect(PartnerRecordResource::getUrl());
                }),

            CreateAction::make()
                ->label('Novo parceiro')
                ->modalHeading('Novo parceiro')
                ->modalWidth(Width::SevenExtraLarge)
                ->modalSubmitActionLabel('Salvar')
                ->createAnother(false)
                ->visible(fn (): bool => app(TenantContext::class)->currentTenantIdForUser(auth()->user()) !== null)
                ->using(function (array $data): PartnerRecord {
                    $officialDocument = $data['official_document'] ?? null;

                    $data['tenant_id'] = app(TenantContext::class)->currentTenantIdForUser(auth()->user());
                    $data['person_type'] = PartnerRecord::personTypeFromOfficialDocument($officialDocument)
                        ?: ($data['person_type'] ?? 'individual');

                    unset($data['official_document']);

                    return DB::transaction(function () use ($data, $officialDocument): PartnerRecord {
                        $record = PartnerRecord::query()->create($data);
                        $record->syncOfficialDocument($officialDocument);

                        return $record->refresh();
                    });
                })
                ->successNotificationTitle('Parceiro criado'),
        ];
    }

    private static function canShowTenantSelector(): bool
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        if ($user->hasRole(config('filament-shield.super_admin.name', 'super_admin'))) {
            return true;
        }

        return app(TenantContext::class)
            ->availableTenantsForUser($user)
            ->count() > 1;
    }

    private static function tenantOptions(): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        $options = app(TenantContext::class)
            ->availableTenantsForUser($user)
            ->mapWithKeys(fn (TenantRecord $tenant): array => [
                $tenant->id => $tenant->name,
            ])
            ->all();

        if ($user->hasRole(config('filament-shield.super_admin.name', 'super_admin'))) {
            return [
                self::GLOBAL_TENANT_OPTION => 'Visão global (todos os grupos)',
            ] + $options;
        }

        return $options;
    }

    private static function currentTenantOption(): ?string
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return null;
        }

        if ($user->hasRole(config('filament-shield.super_admin.name', 'super_admin'))) {
            return app(TenantContext::class)->selectedTenantId() ?? self::GLOBAL_TENANT_OPTION;
        }

        return app(TenantContext::class)->currentTenantIdForUser($user);
    }

    private static function currentTenantLabel(): string
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return 'não definido';
        }

        $tenant = app(TenantContext::class)->currentTenantForUser($user);

        if ($tenant instanceof TenantRecord) {
            return $tenant->name;
        }

        if ($user->hasRole(config('filament-shield.super_admin.name', 'super_admin'))) {
            return 'Visão global';
        }

        return 'não definido';
    }
}
