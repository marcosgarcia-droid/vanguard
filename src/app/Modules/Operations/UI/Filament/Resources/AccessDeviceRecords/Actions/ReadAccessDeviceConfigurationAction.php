<?php

namespace App\Modules\Operations\UI\Filament\Resources\AccessDeviceRecords\Actions;

use App\Modules\Operations\Application\AccessControl\AccessControlRuntime;
use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read\ReadAccessDeviceConfigurationCommand;
use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read\ReadAccessDeviceConfigurationException;
use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read\ReadAccessDeviceConfigurationUseCase;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceConfigurationSource;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;

final class ReadAccessDeviceConfigurationAction
{
    public static function make(): Action
    {
        return Action::make(
            'readAccessDeviceConfiguration'
        )
            ->label('Ler configurações')
            ->tooltip(
                fn (
                    ?AccessDeviceRecord $record
                ): string => self::tooltip($record)
            )
            ->disabled(
                fn (
                    ?AccessDeviceRecord $record
                ): bool => self::isDisabled($record)
            )
            ->icon('heroicon-o-arrow-path')
            ->iconButton()
            ->color('info')
            ->requiresConfirmation()
            ->modalHeading(
                fn (
                    AccessDeviceRecord $record
                ): string => 'Ler configurações - '
                    .$record->display_name
            )
            ->modalDescription(
                'Serão executadas somente consultas de leitura. Nenhuma configuração, pessoa, face, regra, porta, relé ou alarme será alterado.'
            )
            ->modalSubmitActionLabel(
                'Ler configurações'
            )
            ->visible(
                fn (
                    AccessDeviceRecord $record
                ): bool => auth()->user()?->can(
                    'readConfiguration',
                    $record
                ) ?? false
            )
            ->action(
                function (
                    AccessDeviceRecord $record
                ): void {
                    Gate::authorize(
                        'readConfiguration',
                        $record
                    );

                    try {
                        $result = app(
                            ReadAccessDeviceConfigurationUseCase::class
                        )->execute(
                            new ReadAccessDeviceConfigurationCommand(
                                deviceId: $record->id,
                                requestedByUserId: auth()->id() !== null
                                        ? (int) auth()->id()
                                        : null,
                                source: AccessDeviceConfigurationSource::Manual,
                            )
                        );

                        $notification =
                            Notification::make()
                                ->title(
                                    $result->isPartial()
                                        ? 'Leitura concluída parcialmente'
                                        : 'Configurações consultadas'
                                )
                                ->body($result->message);

                        if ($result->isPartial()) {
                            $notification->warning();
                        } else {
                            $notification->success();
                        }

                        $notification->send();
                    } catch (
                        ReadAccessDeviceConfigurationException $exception
                    ) {
                        Notification::make()
                            ->title(
                                'Não foi possível ler as configurações'
                            )
                            ->body(
                                $exception->getMessage()
                            )
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }
            );
    }

    private static function isDisabled(
        ?AccessDeviceRecord $record
    ): bool {
        if (! $record instanceof AccessDeviceRecord) {
            return true;
        }

        return match (
            strtolower((string) $record->provider)
        ) {
            'simulator' => ! (bool) config(
                'access_control.simulator_enabled',
                false
            ),
            'intelbras' => ! app(
                AccessControlRuntime::class
            )->allowsReads(),
            default => true,
        };
    }

    private static function tooltip(
        ?AccessDeviceRecord $record
    ): string {
        if (! $record instanceof AccessDeviceRecord) {
            return 'Leitura indisponível';
        }

        if (
            strtolower((string) $record->provider)
            === 'simulator'
        ) {
            return self::isDisabled($record)
                ? 'Simulador desativado neste ambiente'
                : 'Ler configurações sintéticas';
        }

        return self::isDisabled($record)
            ? 'Leituras reais desativadas neste ambiente'
            : 'Ler configurações';
    }
}
