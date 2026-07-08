<?php

namespace App\Modules\Identity\UI\Filament\Resources\EmployeeWorkScheduleTemplateRecords\Schemas;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeWorkScheduleTemplateRecord;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class EmployeeWorkScheduleTemplateRecordInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(6)
            ->components([
                Tabs::make('Visualização da jornada')
                    ->id('employee-work-schedule-template-infolist-tabs')
                    ->persistTab()
                    ->tabs([
                        Tab::make('Jornada')
                            ->schema([
                                Section::make('Dados principais')
                                    ->columns(6)
                                    ->schema([
                                        TextEntry::make('name')
                                            ->label('Nome')
                                            ->columnSpan(2),

                                        TextEntry::make('type_display')
                                            ->label('Tipo')
                                            ->columnSpan(1),

                                        TextEntry::make('status_display')
                                            ->label('Status')
                                            ->badge()
                                            ->columnSpan(1),

                                        IconEntry::make('is_system')
                                            ->label('Padrão do sistema')
                                            ->boolean()
                                            ->columnSpan(2),

                                        TextEntry::make('description')
                                            ->label('Descrição')
                                            ->placeholder('-')
                                            ->columnSpanFull(),

                                        TextEntry::make('weekly_workload_minutes')
                                            ->label('Carga semanal')
                                            ->formatStateUsing(fn (?int $state): string => self::minutesDisplay($state))
                                            ->placeholder('-')
                                            ->columnSpan(2),

                                        TextEntry::make('daily_workload_minutes')
                                            ->label('Carga diária')
                                            ->formatStateUsing(fn (?int $state): string => self::minutesDisplay($state))
                                            ->placeholder('-')
                                            ->columnSpan(2),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Controle de acesso')
                            ->schema([
                                Section::make('Controle de acesso')
                                    ->description('Liberação antecipada antes do início da jornada.')
                                    ->columns(6)
                                    ->schema([
                                        TextEntry::make('tolerance_before_start_minutes')
                                            ->label('Liberação antecipada de entrada')
                                            ->suffix(' min')
                                            ->columnSpan(3),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Regras da semana')
                            ->schema([
                                Section::make('Regras detalhadas')
                                    ->schema([
                                        TextEntry::make('days_summary')
                                            ->label('Dias')
                                            ->state(fn (EmployeeWorkScheduleTemplateRecord $record): string => self::daysSummary($record))
                                            ->placeholder('Nenhuma regra detalhada cadastrada')
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
                                            ->placeholder('Nenhuma observação cadastrada')
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    private static function daysSummary(EmployeeWorkScheduleTemplateRecord $record): string
    {
        $record->loadMissing('days');

        if ($record->days->isEmpty()) {
            return 'Nenhuma regra detalhada cadastrada';
        }

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
                && $groups[$lastIndex]['key'] === $key
                && (int) $groups[$lastIndex]['weekday_to'] + 1 === (int) $day->weekday
            ) {
                $groups[$lastIndex]['weekday_to'] = (int) $day->weekday;

                continue;
            }

            $groups[] = [
                'key' => $key,
                'weekday_from' => (int) $day->weekday,
                'weekday_to' => (int) $day->weekday,
                'is_working_day' => (bool) $day->is_working_day,
                'work_starts_at' => self::timeValue($day->work_starts_at),
                'break_starts_at' => self::timeValue($day->break_starts_at),
                'break_ends_at' => self::timeValue($day->break_ends_at),
                'work_ends_at' => self::timeValue($day->work_ends_at),
                'notes' => $day->notes,
            ];
        }

        return collect($groups)
            ->map(function (array $group): string {
                $days = self::weekdayRange($group['weekday_from'], $group['weekday_to']);

                if (! $group['is_working_day']) {
                    return $days.': DSR';
                }

                $hours = collect([
                    $group['work_starts_at'],
                    filled($group['break_starts_at']) ? 'às '.$group['break_starts_at'] : null,
                    filled($group['break_ends_at']) ? '- '.$group['break_ends_at'] : null,
                    filled($group['work_ends_at']) ? 'às '.$group['work_ends_at'] : null,
                ])->filter()->implode(' ');

                return $days.': '.$hours;
            })
            ->implode("\n");
    }

    private static function weekdayRange(int $from, int $to): string
    {
        if ($from === $to) {
            return self::weekdayShortName($from);
        }

        return self::weekdayShortName($from).' a '.self::weekdayShortName($to);
    }

    private static function weekdayShortName(?int $weekday): string
    {
        return match ($weekday) {
            1 => 'SEG',
            2 => 'TER',
            3 => 'QUA',
            4 => 'QUI',
            5 => 'SEX',
            6 => 'SAB',
            7 => 'DOM',
            default => '-',
        };
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

    private static function timeValue(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return substr((string) $value, 0, 5);
    }
}
