<?php

namespace App\Modules\Identity\UI\Filament\Resources\EmployeeRecords\Schemas;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeAddressRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EmployeeRecordInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(6)
            ->components([
                Section::make('Funcionário')
                    ->columns(6)
                    ->schema([
                        TextEntry::make('photo_path')
                            ->label('Foto')
                            ->state(fn (EmployeeRecord $record): string => filled($record->photo_path) ? 'Foto cadastrada' : '-')
                            ->columnSpan(1),

                        TextEntry::make('employee_code')
                            ->label('Matrícula')
                            ->placeholder('-')
                            ->columnSpan(1),

                        TextEntry::make('full_name')
                            ->label('Nome completo')
                            ->columnSpan(2),

                        TextEntry::make('preferred_name')
                            ->label('Nome de uso')
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

                Section::make('Vínculos')
                    ->columns(6)
                    ->schema([
                        TextEntry::make('tenant.name')
                            ->label('Tenant')
                            ->placeholder('-')
                            ->columnSpan(2),

                        TextEntry::make('organization.display_name')
                            ->label('Unidade')
                            ->state(fn (EmployeeRecord $record): string => $record->organization?->operational_name ?? '-')
                            ->columnSpan(2),

                        TextEntry::make('manager.full_name')
                            ->label('Gestor responsável')
                            ->placeholder('-')
                            ->columnSpan(1),

                        TextEntry::make('user.email')
                            ->label('Usuário vinculado')
                            ->placeholder('-')
                            ->columnSpan(1),
                    ])
                    ->columnSpanFull(),

                Section::make('Dados profissionais')
                    ->columns(6)
                    ->schema([
                        TextEntry::make('department')
                            ->label('Departamento')
                            ->placeholder('-')
                            ->columnSpan(2),

                        TextEntry::make('position')
                            ->label('Cargo')
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

                Section::make('Jornada atual')
                    ->columns(6)
                    ->schema([
                        TextEntry::make('current_work_schedule')
                            ->label('Jornada')
                            ->state(fn (EmployeeRecord $record): string => self::scheduleSummary($record))
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('Observações')
                    ->schema([
                        TextEntry::make('notes')
                            ->label('Observações')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    private static function statusLabel(?string $status): string
    {
        return match ($status) {
            'active' => 'Ativo',
            'inactive' => 'Inativo',
            'terminated' => 'Desligado',
            default => $status ?: '-',
        };
    }

    private static function employmentTypeLabel(?string $type): string
    {
        return match ($type) {
            'employee' => 'Funcionário',
            'contractor' => 'Prestador',
            'intern' => 'Estagiário',
            'temporary' => 'Temporário',
            default => $type ?: '-',
        };
    }

    private static function genderLabel(?string $gender): string
    {
        return match ($gender) {
            'female' => 'Feminino',
            'male' => 'Masculino',
            'not_informed' => 'Não informado',
            'other' => 'Outro',
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

    private static function formatCep(?string $postalCode): string
    {
        $postalCode = preg_replace('/\D+/', '', (string) $postalCode);

        if (strlen($postalCode) !== 8) {
            return $postalCode ?: '-';
        }

        return substr($postalCode, 0, 5).'-'.substr($postalCode, 5);
    }

    private static function addressLine(?EmployeeAddressRecord $address): string
    {
        if ($address === null) {
            return '-';
        }

        return collect([
            $address->street,
            $address->number,
            $address->complement,
            $address->district,
        ])->filter()->implode(', ') ?: '-';
    }

    private static function cityState(?EmployeeAddressRecord $address): string
    {
        if ($address === null) {
            return '-';
        }

        return collect([
            $address->city,
            $address->state,
        ])->filter()->implode('/');
    }

    private static function scheduleSummary(EmployeeRecord $record): string
    {
        $schedule = $record->currentWorkSchedule();

        if ($schedule === null) {
            return '-';
        }

        $weekly = $schedule->weekly_workload_minutes
            ? self::minutesToHours((int) $schedule->weekly_workload_minutes)
            : 'não informada';

        $tolerance = (int) $schedule->tolerance_before_start_minutes;

        return "{$schedule->name} — carga semanal: {$weekly}; tolerância antes do início: {$tolerance} min.";
    }

    private static function minutesToHours(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        return $remaining > 0
            ? "{$hours}h{$remaining}min"
            : "{$hours}h";
    }
}
