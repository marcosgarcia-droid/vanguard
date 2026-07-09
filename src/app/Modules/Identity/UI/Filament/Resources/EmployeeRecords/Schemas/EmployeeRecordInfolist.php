<?php

namespace App\Modules\Identity\UI\Filament\Resources\EmployeeRecords\Schemas;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeAddressRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeWorkScheduleRecord;
use App\Support\VanguardText;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class EmployeeRecordInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(6)
            ->components([
                Tabs::make('Visualização do funcionário')
                    ->id('employee-record-infolist-tabs')
                    ->persistTab()
                    ->tabs([
                        Tab::make('Funcionário')
                            ->schema([
                                Section::make('Dados principais')
                                    ->columns(6)
                                    ->schema([
                                        TextEntry::make('photo_path')
                                            ->label('Foto')
                                            ->state(fn (EmployeeRecord $record): string => filled($record->photo_path) ? 'Foto cadastrada' : '-')
                                            ->columnSpan(1),

                                        TextEntry::make('employee_code')
                                            ->label('Matrícula')
                                            ->formatStateUsing(fn (?string $state): string => VanguardText::upper($state))
                                            ->placeholder('-')
                                            ->columnSpan(1),

                                        TextEntry::make('full_name')
                                            ->label('Nome completo')
                                            ->formatStateUsing(fn (?string $state): string => VanguardText::upper($state))
                                            ->columnSpan(2),

                                        TextEntry::make('preferred_name')
                                            ->label('Nome de uso')
                                            ->formatStateUsing(fn (?string $state): string => VanguardText::upper($state))
                                            ->placeholder('-')
                                            ->columnSpan(2),

                                        TextEntry::make('status')
                                            ->label('Status')
                                            ->badge()
                                            ->formatStateUsing(fn (?string $state): string => self::statusLabel($state))
                                            ->columnSpan(2),

                                        TextEntry::make('employment_type')
                                            ->label('Tipo de vínculo')
                                            ->badge()
                                            ->formatStateUsing(fn (?string $state): string => self::employmentTypeLabel($state))
                                            ->columnSpan(2),

                                        TextEntry::make('gender')
                                            ->label('Sexo')
                                            ->formatStateUsing(fn (?string $state): string => self::genderLabel($state))
                                            ->placeholder('-')
                                            ->columnSpan(2),
                                    ])
                                    ->columnSpanFull(),

                                Section::make('Dados profissionais')
                                    ->columns(6)
                                    ->schema([
                                        TextEntry::make('department')
                                            ->label('Departamento')
                                            ->formatStateUsing(fn (?string $state): string => VanguardText::upper($state))
                                            ->placeholder('-')
                                            ->columnSpan(2),

                                        TextEntry::make('position')
                                            ->label('Cargo')
                                            ->formatStateUsing(fn (?string $state): string => VanguardText::upper($state))
                                            ->placeholder('-')
                                            ->columnSpan(2),

                                        TextEntry::make('hired_at')
                                            ->label('Admissão')
                                            ->date('d/m/Y')
                                            ->placeholder('-')
                                            ->columnSpan(1),

                                        TextEntry::make('terminated_at')
                                            ->label('Desligamento')
                                            ->date('d/m/Y')
                                            ->placeholder('-')
                                            ->columnSpan(1),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Vínculos')
                            ->schema([
                                Section::make('Vínculos')
                                    ->columns(6)
                                    ->schema([
                                        TextEntry::make('tenant.name')
                                            ->label('Grupo empresarial')
                                            ->formatStateUsing(fn (?string $state): string => VanguardText::upper($state))
                                            ->placeholder('-')
                                            ->columnSpan(2),

                                        TextEntry::make('organization.display_name')
                                            ->label('Unidade')
                                            ->state(fn (EmployeeRecord $record): string => VanguardText::upper($record->organization?->operational_name))
                                            ->columnSpan(2),

                                        TextEntry::make('manager.full_name')
                                            ->label('Gestor responsável')
                                            ->formatStateUsing(fn (?string $state): string => VanguardText::upper($state))
                                            ->placeholder('-')
                                            ->columnSpan(1),

                                        TextEntry::make('user.email')
                                            ->label('Usuário vinculado')
                                            ->placeholder('-')
                                            ->columnSpan(1),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Documentos e contatos')
                            ->schema([
                                Section::make('Documentos e contatos')
                                    ->columns(6)
                                    ->schema([
                                        TextEntry::make('cpf')
                                            ->label('CPF')
                                            ->formatStateUsing(fn (?string $state): string => self::formatCpf($state))
                                            ->placeholder('-')
                                            ->columnSpan(2),

                                        TextEntry::make('mobile_phone')
                                            ->label('Celular')
                                            ->formatStateUsing(fn (?string $state): string => self::formatPhone($state))
                                            ->placeholder('-')
                                            ->columnSpan(2),

                                        TextEntry::make('phone')
                                            ->label('Telefone')
                                            ->formatStateUsing(fn (?string $state): string => self::formatPhone($state))
                                            ->placeholder('-')
                                            ->columnSpan(2),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Endereço')
                            ->schema([
                                Section::make('Endereço principal')
                                    ->columns(6)
                                    ->schema([
                                        TextEntry::make('primary_address_line')
                                            ->label('Endereço')
                                            ->state(fn (EmployeeRecord $record): string => self::addressLine($record->primaryAddress()))
                                            ->columnSpan(4),

                                        TextEntry::make('primary_address_postal_code')
                                            ->label('CEP')
                                            ->state(fn (EmployeeRecord $record): string => self::formatCep($record->primaryAddress()?->postal_code))
                                            ->columnSpan(1),

                                        TextEntry::make('primary_address_city_state')
                                            ->label('Cidade/UF')
                                            ->state(fn (EmployeeRecord $record): string => self::cityState($record->primaryAddress()))
                                            ->columnSpan(1),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Jornada')
                            ->schema([
                                Section::make('Jornada atual')
                                    ->columns(6)
                                    ->schema([
                                        TextEntry::make('current_work_schedule')
                                            ->label('Jornada')
                                            ->state(fn (EmployeeRecord $record): string => self::scheduleSummary($record))
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Observações')
                            ->schema([
                                Section::make('Observações')
                                    ->schema([
                                        TextEntry::make('notes')
                                            ->label('Observações')
                                            ->placeholder('-')
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    private static function statusLabel(?string $status): string
    {
        return match ($status) {
            'active' => 'ATIVO',
            'inactive' => 'INATIVO',
            'terminated' => 'DESLIGADO',
            default => $status ?: '-',
        };
    }

    private static function employmentTypeLabel(?string $type): string
    {
        return match ($type) {
            'employee' => 'FUNCIONÁRIO',
            'contractor' => 'PRESTADOR',
            'intern' => 'ESTAGIÁRIO',
            'temporary' => 'TEMPORÁRIO',
            default => $type ?: '-',
        };
    }

    private static function genderLabel(?string $gender): string
    {
        return match ($gender) {
            'female' => 'FEMININO',
            'male' => 'MASCULINO',
            'not_informed' => 'NÃO INFORMADO',
            'other' => 'OUTRO',
            default => $gender ?: '-',
        };
    }

    private static function formatCpf(?string $cpf): string
    {
        $cpf = preg_replace('/\D+/', '', (string) $cpf);

        if (strlen($cpf) !== 11) {
            return $cpf ?: '-';
        }

        return substr($cpf, 0, 3).'.'.substr($cpf, 3, 3).'.'.substr($cpf, 6, 3).'-'.substr($cpf, 9, 2);
    }

    private static function formatPhone(?string $phone): string
    {
        $phone = preg_replace('/\D+/', '', (string) $phone);

        if (strlen($phone) === 11) {
            return '('.substr($phone, 0, 2).') '.substr($phone, 2, 5).'-'.substr($phone, 7);
        }

        if (strlen($phone) === 10) {
            return '('.substr($phone, 0, 2).') '.substr($phone, 2, 4).'-'.substr($phone, 6);
        }

        return $phone ?: '-';
    }

    private static function formatCep(?string $cep): string
    {
        $cep = preg_replace('/\D+/', '', (string) $cep);

        if (strlen($cep) !== 8) {
            return $cep ?: '-';
        }

        return substr($cep, 0, 5).'-'.substr($cep, 5);
    }

    private static function addressLine(?EmployeeAddressRecord $address): string
    {
        if (! $address) {
            return '-';
        }

        $line = collect([
            $address->street,
            $address->number,
            $address->complement,
            $address->district,
        ])->filter()->implode(', ');

        return VanguardText::upper($line);
    }

    private static function cityState(?EmployeeAddressRecord $address): string
    {
        if (! $address) {
            return '-';
        }

        $cityState = collect([
            $address->city,
            $address->state,
        ])->filter()->implode('/');

        return VanguardText::upper($cityState);
    }

    private static function scheduleSummary(EmployeeRecord $record): string
    {
        $schedule = $record->currentWorkSchedule();

        if (! $schedule) {
            return '-';
        }

        $schedule->loadMissing('template');

        $parts = [
            self::scheduleName($schedule),
            self::scheduleDescription($schedule),
            self::scheduleWorkload($schedule),
            self::scheduleValidity($schedule),
        ];

        return collect($parts)->filter()->implode("\n");
    }

    private static function scheduleName(EmployeeWorkScheduleRecord $schedule): string
    {
        return VanguardText::upper($schedule->template?->name
            ?: $schedule->name
            ?: 'Jornada principal');
    }

    private static function scheduleDescription(EmployeeWorkScheduleRecord $schedule): ?string
    {
        return filled($schedule->template?->description)
            ? VanguardText::upper($schedule->template->description)
            : null;
    }

    private static function scheduleWorkload(EmployeeWorkScheduleRecord $schedule): ?string
    {
        $items = [];

        if ($schedule->weekly_workload_minutes) {
            $items[] = 'Carga semanal: '.self::minutesDisplay($schedule->weekly_workload_minutes);
        }

        if ($schedule->daily_workload_minutes) {
            $items[] = 'Carga diária: '.self::minutesDisplay($schedule->daily_workload_minutes);
        }

        if ($schedule->tolerance_before_start_minutes) {
            $items[] = 'Liberação antecipada: '.$schedule->tolerance_before_start_minutes.' min';
        }

        return $items !== [] ? implode(' | ', $items) : null;
    }

    private static function scheduleValidity(EmployeeWorkScheduleRecord $schedule): ?string
    {
        if (! $schedule->valid_from && ! $schedule->valid_until) {
            return null;
        }

        $from = $schedule->valid_from?->format('d/m/Y') ?? '-';
        $until = $schedule->valid_until?->format('d/m/Y') ?? 'sem término';

        return VanguardText::upper('Vigência: '.$from.' até '.$until);
    }

    private static function minutesDisplay(?int $minutes): string
    {
        if (! $minutes) {
            return '-';
        }

        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        return sprintf('%dh%02d', $hours, $remainingMinutes);
    }
}
