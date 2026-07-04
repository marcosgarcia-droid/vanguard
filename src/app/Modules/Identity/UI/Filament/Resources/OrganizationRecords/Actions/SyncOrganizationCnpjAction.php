<?php

namespace App\Modules\Identity\UI\Filament\Resources\OrganizationRecords\Actions;

use App\Modules\Identity\Application\Organizations\RegistrationData\SyncOrganizationRegistrationDataFromCnpjLookup\SyncOrganizationRegistrationDataFromCnpjLookupCommand;
use App\Modules\Identity\Application\Organizations\RegistrationData\SyncOrganizationRegistrationDataFromCnpjLookup\SyncOrganizationRegistrationDataFromCnpjLookupUseCase;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Throwable;

final class SyncOrganizationCnpjAction
{
    public static function make(
        string $name = 'syncOrganizationCnpj',
        bool $iconButton = true,
    ): Action {
        $action = Action::make($name)
            ->label('Sincronizar CNPJ')
            ->tooltip('Sincronizar CNPJ')
            ->icon('heroicon-o-arrow-path')
            ->requiresConfirmation()
            ->modalHeading('Sincronizar CNPJ')
            ->modalDescription('A organização será atualizada com os dados cadastrais retornados pelos providers configurados.')
            ->modalSubmitActionLabel('Sincronizar')
            ->visible(fn (OrganizationRecord $record): bool => filled($record->cnpj))
            ->action(function (OrganizationRecord $record): void {
                try {
                    app(SyncOrganizationRegistrationDataFromCnpjLookupUseCase::class)
                        ->execute(new SyncOrganizationRegistrationDataFromCnpjLookupCommand(
                            organizationId: $record->id,
                            cnpj: (string) $record->cnpj,
                        ));

                    $record->refresh();

                    Notification::make()
                        ->title('Dados cadastrais sincronizados')
                        ->body('A organização foi atualizada a partir da consulta CNPJ.')
                        ->success()
                        ->send();
                } catch (Throwable $exception) {
                    report($exception);

                    Notification::make()
                        ->title('Não foi possível sincronizar o CNPJ')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();
                }
            });

        return $iconButton
            ? $action->iconButton()
            : $action;
    }
}
