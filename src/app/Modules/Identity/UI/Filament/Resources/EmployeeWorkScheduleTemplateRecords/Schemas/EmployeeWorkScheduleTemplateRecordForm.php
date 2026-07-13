<?php

namespace App\Modules\Identity\UI\Filament\Resources\EmployeeWorkScheduleTemplateRecords\Schemas;

use App\Modules\Identity\Application\Tenancy\TenantContext;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeWorkScheduleTemplateRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class EmployeeWorkScheduleTemplateRecordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(6)
            ->components([
                Hidden::make('id')
                    ->default(fn (): string => (string) Str::uuid())
                    ->required(),

                Hidden::make('code')
                    ->dehydrateStateUsing(fn (?string $state, $get): ?string => filled($state)
                        ? $state
                        : $get('name')),

                Tabs::make('Cadastro da jornada')
                    ->id('employee-work-schedule-template-form-tabs')
                    ->persistTab()
                    ->tabs([
                        Tab::make('Jornada')
                            ->schema([
                                Section::make('Dados principais')
                                    ->description('Cadastre a carga horária que será selecionada no cadastro do funcionário.')
                                    ->columns(6)
                                    ->schema([
                                        Select::make('tenant_id')
                                            ->label('Grupo empresarial')
                                            ->helperText('Obrigatório quando estiver na Visão Global.')
                                            ->options(fn (): array => self::tenantOptions())
                                            ->default(
                                                fn (): ?string => app(TenantContext::class)
                                                    ->currentTenantIdForUser(auth()->user())
                                            )
                                            ->required(
                                                fn (): bool => self::requiresTenantSelection()
                                            )
                                            ->visible(
                                                fn (?EmployeeWorkScheduleTemplateRecord $record): bool => $record === null
                                                    && self::requiresTenantSelection()
                                            )
                                            ->searchable()
                                            ->preload()
                                            ->native(false)
                                            ->columnSpan(3),

                                        TextInput::make('name')
                                            ->label('Nome')
                                            ->placeholder('Ex: Administrativo 44h')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpan(3),

                                        Select::make('status')
                                            ->label('Status')
                                            ->options([
                                                'active' => 'Ativa',
                                                'inactive' => 'Inativa',
                                            ])
                                            ->required()
                                            ->default('active')
                                            ->native(false)
                                            ->columnSpan(1),

                                        Select::make('type')
                                            ->label('Tipo')
                                            ->options([
                                                'standard' => 'Padrão',
                                                'flexible' => 'Flexível',
                                                'shift_12x36' => 'Escala 12x36',
                                                'custom' => 'Personalizada',
                                            ])
                                            ->required()
                                            ->default('standard')
                                            ->native(false)
                                            ->columnSpan(2),

                                        TextInput::make('weekly_workload_hours')
                                            ->label('Carga semanal')
                                            ->helperText('Horas semanais. Ex: 44.')
                                            ->numeric()
                                            ->minValue(0)
                                            ->columnSpan(2),

                                        TextInput::make('weekly_workload_remaining_minutes')
                                            ->label('Minutos')
                                            ->helperText('Minutos adicionais.')
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(59)
                                            ->default(0)
                                            ->columnSpan(1),

                                        TextInput::make('daily_workload_hours')
                                            ->label('Carga diária')
                                            ->helperText('Horas diárias. Ex: 8.')
                                            ->numeric()
                                            ->minValue(0)
                                            ->columnSpan(2),

                                        TextInput::make('daily_workload_remaining_minutes')
                                            ->label('Minutos')
                                            ->helperText('Minutos adicionais. Ex: 48.')
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(59)
                                            ->default(0)
                                            ->columnSpan(1),

                                        Textarea::make('description')
                                            ->label('Descrição exibida ao usuário')
                                            ->placeholder('08:00 às 12:00 - 13:00 às 17:48 - SAB DOM DSR')
                                            ->helperText('Essa é a descrição simples que aparecerá no cadastro do funcionário.')
                                            ->rows(3)
                                            ->required()
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Controle de acesso')
                            ->schema([
                                Section::make('Controle de acesso')
                                    ->description('Define a liberação antecipada antes do início da jornada. Tolerâncias de ponto/CLT serão tratadas em regra própria.')
                                    ->columns(6)
                                    ->schema([
                                        TextInput::make('tolerance_before_start_minutes')
                                            ->label('Liberação antecipada de entrada')
                                            ->helperText('Em minutos. Ex: 30 permite acesso até 30 minutos antes do turno.')
                                            ->numeric()
                                            ->default(0)
                                            ->minValue(0)
                                            ->maxValue(30)
                                            ->columnSpan(3),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Regras da semana')
                            ->schema([
                                Section::make('Regras agrupadas')
                                    ->description('Preencha uma regra para vários dias. Ex: segunda a sexta com o mesmo horário.')
                                    ->schema([
                                        Repeater::make('weekly_rule_groups')
                                            ->label('Regras da jornada')
                                            ->defaultItems(0)
                                            ->addActionLabel('Adicionar regra agrupada')
                                            ->reorderable(false)
                                            ->columns(6)
                                            ->schema([
                                                Select::make('weekday_from')
                                                    ->label('De')
                                                    ->options(self::weekdayOptions())
                                                    ->required()
                                                    ->native(false)
                                                    ->columnSpan(1),

                                                Select::make('weekday_to')
                                                    ->label('Até')
                                                    ->options(self::weekdayOptions())
                                                    ->required()
                                                    ->native(false)
                                                    ->columnSpan(1),

                                                Toggle::make('is_working_day')
                                                    ->label('Dia trabalhado')
                                                    ->default(true)
                                                    ->live()
                                                    ->dehydrateStateUsing(fn (mixed $state): bool => (bool) $state)
                                                    ->columnSpan(2),

                                                Toggle::make('ends_next_day')
                                                    ->label('Vira o dia')
                                                    ->default(false)
                                                    ->dehydrateStateUsing(fn (mixed $state): bool => (bool) $state)
                                                    ->columnSpan(2),

                                                TimePicker::make('work_starts_at')
                                                    ->label('Entrada')
                                                    ->seconds(false)
                                                    ->columnSpan(1),

                                                TimePicker::make('break_starts_at')
                                                    ->label('Início intervalo')
                                                    ->seconds(false)
                                                    ->columnSpan(1),

                                                TimePicker::make('break_ends_at')
                                                    ->label('Fim intervalo')
                                                    ->seconds(false)
                                                    ->columnSpan(1),

                                                TimePicker::make('work_ends_at')
                                                    ->label('Saída prevista')
                                                    ->seconds(false)
                                                    ->columnSpan(1),

                                                Textarea::make('notes')
                                                    ->label('Observações')
                                                    ->placeholder('Ex: DSR')
                                                    ->rows(2)
                                                    ->columnSpan(2),
                                            ])
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Observações')
                            ->schema([
                                Section::make('Observações')
                                    ->schema([
                                        Textarea::make('notes')
                                            ->label('Observações internas')
                                            ->rows(6)
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function hydrateTransientFields(array $data, ?EmployeeWorkScheduleTemplateRecord $record = null): array
    {
        $data['weekly_workload_hours'] = self::hoursFromMinutes($record?->weekly_workload_minutes);
        $data['weekly_workload_remaining_minutes'] = self::remainingMinutes($record?->weekly_workload_minutes);
        $data['daily_workload_hours'] = self::hoursFromMinutes($record?->daily_workload_minutes);
        $data['daily_workload_remaining_minutes'] = self::remainingMinutes($record?->daily_workload_minutes);
        $data['weekly_rule_groups'] = $record ? self::ruleGroupsFromRecord($record) : [];

        return $data;
    }

    public static function normalizeData(array $data): array
    {
        $data['weekly_workload_minutes'] = self::toMinutes(
            $data['weekly_workload_hours'] ?? null,
            $data['weekly_workload_remaining_minutes'] ?? null,
        );

        $data['daily_workload_minutes'] = self::toMinutes(
            $data['daily_workload_hours'] ?? null,
            $data['daily_workload_remaining_minutes'] ?? null,
        );

        $data['tolerance_after_end_minutes'] = 0;

        if (blank($data['code'] ?? null) && filled($data['name'] ?? null)) {
            $data['code'] = $data['name'];
        }

        unset(
            $data['weekly_workload_hours'],
            $data['weekly_workload_remaining_minutes'],
            $data['daily_workload_hours'],
            $data['daily_workload_remaining_minutes'],
            $data['weekly_rule_groups'],
        );

        return $data;
    }

    public static function syncGeneratedDays(EmployeeWorkScheduleTemplateRecord $record, array $ruleGroups): void
    {
        $record->days()->delete();

        foreach ($ruleGroups as $group) {
            $weekdayFrom = (int) ($group['weekday_from'] ?? 0);
            $weekdayTo = (int) ($group['weekday_to'] ?? 0);

            if ($weekdayFrom < 1 || $weekdayTo < 1) {
                continue;
            }

            if ($weekdayFrom > $weekdayTo) {
                [$weekdayFrom, $weekdayTo] = [$weekdayTo, $weekdayFrom];
            }

            $isWorkingDay = filter_var($group['is_working_day'] ?? true, FILTER_VALIDATE_BOOLEAN);

            foreach (range($weekdayFrom, $weekdayTo) as $weekday) {
                $record->days()->create([
                    'weekday' => $weekday,
                    'sequence' => 1,
                    'is_working_day' => $isWorkingDay,
                    'work_starts_at' => $isWorkingDay ? self::timeValue($group['work_starts_at'] ?? null) : null,
                    'work_ends_at' => $isWorkingDay ? self::timeValue($group['work_ends_at'] ?? null) : null,
                    'break_starts_at' => $isWorkingDay ? self::timeValue($group['break_starts_at'] ?? null) : null,
                    'break_ends_at' => $isWorkingDay ? self::timeValue($group['break_ends_at'] ?? null) : null,
                    'ends_next_day' => filter_var($group['ends_next_day'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'notes' => $group['notes'] ?? null,
                ]);
            }
        }
    }

    private static function requiresTenantSelection(): bool
    {
        $user = auth()->user();

        return $user?->hasRole(
            config('filament-shield.super_admin.name', 'super_admin')
        )
            && app(TenantContext::class)
                ->currentTenantIdForUser($user) === null;
    }

    /**
     * @return array<string, string>
     */
    private static function tenantOptions(): array
    {
        return TenantRecord::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private static function weekdayOptions(): array
    {
        return [
            1 => 'Segunda-feira',
            2 => 'Terça-feira',
            3 => 'Quarta-feira',
            4 => 'Quinta-feira',
            5 => 'Sexta-feira',
            6 => 'Sábado',
            7 => 'Domingo',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function ruleGroupsFromRecord(EmployeeWorkScheduleTemplateRecord $record): array
    {
        $record->loadMissing('days');

        $groups = [];

        foreach ($record->days->sortBy([['weekday', 'asc'], ['sequence', 'asc']]) as $day) {
            $key = implode('|', [
                (int) $day->is_working_day,
                self::timeValue($day->work_starts_at),
                self::timeValue($day->break_starts_at),
                self::timeValue($day->break_ends_at),
                self::timeValue($day->work_ends_at),
                (int) $day->ends_next_day,
                (string) $day->notes,
            ]);

            $lastIndex = array_key_last($groups);

            if (
                $lastIndex !== null
                && $groups[$lastIndex]['_key'] === $key
                && (int) $groups[$lastIndex]['weekday_to'] + 1 === (int) $day->weekday
            ) {
                $groups[$lastIndex]['weekday_to'] = (int) $day->weekday;

                continue;
            }

            $groups[] = [
                '_key' => $key,
                'weekday_from' => (int) $day->weekday,
                'weekday_to' => (int) $day->weekday,
                'is_working_day' => (bool) $day->is_working_day,
                'work_starts_at' => self::timeValue($day->work_starts_at),
                'break_starts_at' => self::timeValue($day->break_starts_at),
                'break_ends_at' => self::timeValue($day->break_ends_at),
                'work_ends_at' => self::timeValue($day->work_ends_at),
                'ends_next_day' => (bool) $day->ends_next_day,
                'notes' => $day->notes,
            ];
        }

        return collect($groups)
            ->map(function (array $group): array {
                unset($group['_key']);

                return $group;
            })
            ->values()
            ->all();
    }

    private static function toMinutes(mixed $hours, mixed $minutes): ?int
    {
        $hours = filled($hours) ? (int) $hours : 0;
        $minutes = filled($minutes) ? (int) $minutes : 0;

        $total = ($hours * 60) + $minutes;

        return $total > 0 ? $total : null;
    }

    private static function hoursFromMinutes(?int $minutes): ?int
    {
        return $minutes ? intdiv($minutes, 60) : null;
    }

    private static function remainingMinutes(?int $minutes): int
    {
        return $minutes ? $minutes % 60 : 0;
    }

    private static function timeValue(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return substr((string) $value, 0, 5);
    }
}
