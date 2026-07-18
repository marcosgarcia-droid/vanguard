<?php

namespace App\Modules\Operations\UI\Filament\Resources\VisitRecords\Schemas;

use App\Modules\Identity\Application\Tenancy\TenantContext;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\PartnerRecord;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class VisitRecordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(6)
            ->components([
                Hidden::make('tenant_id')
                    ->default(
                        fn (): ?string => app(TenantContext::class)
                            ->currentTenantIdForUser(auth()->user())
                    ),

                Hidden::make('status')
                    ->default(VisitStatus::Scheduled->value)
                    ->required(),

                Tabs::make('Agendamento da visita')
                    ->id('visit-record-form-tabs')
                    ->persistTab()
                    ->tabs([
                        Tab::make('Visita')
                            ->schema([
                                Section::make('Dados da visita')
                                    ->description('Informe a unidade, o visitante e a finalidade do acesso.')
                                    ->columns(6)
                                    ->schema([
                                        Select::make('organization_id')
                                            ->label('Unidade')
                                            ->options(
                                                fn (): array => self::organizationOptions()
                                            )
                                            ->required()
                                            ->searchable()
                                            ->preload()
                                            ->live()
                                            ->afterStateUpdated(function ($set): void {
                                                $set('visitor_id', null);
                                                $set('host_employee_id', null);
                                                $set('partner_id', null);
                                            })
                                            ->native(false)
                                            ->columnSpan(3),

                                        Select::make('visitor_id')
                                            ->label('Visitante')
                                            ->helperText('São exibidos apenas visitantes ativos da unidade selecionada.')
                                            ->options(
                                                fn ($get): array => self::visitorOptions(
                                                    $get('organization_id')
                                                )
                                            )
                                            ->required()
                                            ->searchable()
                                            ->preload()
                                            ->live()
                                            ->afterStateUpdated(function ($state, $set): void {
                                                $partnerId = filled($state)
                                                    ? VisitorRecord::query()
                                                        ->whereKey($state)
                                                        ->value('partner_id')
                                                    : null;

                                                $set('partner_id', $partnerId);
                                            })
                                            ->native(false)
                                            ->columnSpan(3),

                                        Select::make('host_employee_id')
                                            ->label('Visitado')
                                            ->helperText('Funcionário responsável por receber o visitante.')
                                            ->options(
                                                fn ($get): array => self::employeeOptions(
                                                    $get('organization_id')
                                                )
                                            )
                                            ->searchable()
                                            ->preload()
                                            ->native(false)
                                            ->columnSpan(3),

                                        Select::make('partner_id')
                                            ->label('Parceiro / empresa representada')
                                            ->options(
                                                fn ($get): array => self::partnerOptions(
                                                    $get('organization_id')
                                                )
                                            )
                                            ->searchable()
                                            ->preload()
                                            ->native(false)
                                            ->columnSpan(3),

                                        TextInput::make('purpose')
                                            ->label('Finalidade da visita')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Data e horário')
                            ->schema([
                                Section::make('Previsão')
                                    ->description('O horário previsto orienta a portaria, mas não bloqueia exceções operacionais.')
                                    ->columns(4)
                                    ->schema([
                                        DateTimePicker::make('expected_start_at')
                                            ->label('Início previsto')
                                            ->seconds(false)
                                            ->default(now()->addHour())
                                            ->required()
                                            ->columnSpan(2),

                                        DateTimePicker::make('expected_end_at')
                                            ->label('Término previsto')
                                            ->seconds(false)
                                            ->afterOrEqual('expected_start_at')
                                            ->columnSpan(2),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Observações')
                            ->schema([
                                Section::make('Observações')
                                    ->schema([
                                        Textarea::make('notes')
                                            ->label('Observações')
                                            ->rows(5)
                                            ->maxLength(5000)
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    private static function organizationOptions(): array
    {
        $user = auth()->user();

        if (! $user) {
            return [];
        }

        $tenantContext = app(TenantContext::class);

        $query = OrganizationRecord::query()
            ->where('status', 'active')
            ->orderBy('display_name');

        $tenantContext->applyTenantScope(
            $query,
            $user
        );

        $tenantContext->applyUserOrganizationScope(
            $query,
            $user
        );

        return $query
            ->get()
            ->mapWithKeys(fn (OrganizationRecord $organization): array => [
                $organization->id => $organization->operational_name,
            ])
            ->all();
    }

    private static function visitorOptions(
        ?string $organizationId
    ): array {
        if (blank($organizationId)) {
            return [];
        }

        return VisitorRecord::query()
            ->where('organization_id', $organizationId)
            ->where('status', VisitorStatus::Active)
            ->orderBy('full_name')
            ->pluck('full_name', 'id')
            ->all();
    }

    private static function employeeOptions(
        ?string $organizationId
    ): array {
        if (blank($organizationId)) {
            return [];
        }

        return EmployeeRecord::query()
            ->where('organization_id', $organizationId)
            ->where('status', 'active')
            ->orderBy('full_name')
            ->pluck('full_name', 'id')
            ->all();
    }

    private static function partnerOptions(
        ?string $organizationId
    ): array {
        if (blank($organizationId)) {
            return [];
        }

        return PartnerRecord::query()
            ->where('organization_id', $organizationId)
            ->where('status', 'active')
            ->orderBy('display_name')
            ->pluck('display_name', 'id')
            ->all();
    }
}
