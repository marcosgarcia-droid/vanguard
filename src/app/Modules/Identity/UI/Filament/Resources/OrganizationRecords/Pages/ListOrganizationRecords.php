<?php

namespace App\Modules\Identity\UI\Filament\Resources\OrganizationRecords\Pages;

use App\Modules\Identity\Application\Organizations\RegistrationData\SyncOrganizationRegistrationDataFromCnpjLookup\SyncOrganizationRegistrationDataFromCnpjLookupCommand;
use App\Modules\Identity\Application\Organizations\RegistrationData\SyncOrganizationRegistrationDataFromCnpjLookup\SyncOrganizationRegistrationDataFromCnpjLookupUseCase;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\UI\Filament\Resources\OrganizationRecords\OrganizationRecordResource;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Throwable;

class ListOrganizationRecords extends ListRecords
{
    protected static string $resource = OrganizationRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nova organização')
                ->modalHeading('Nova organização')
                ->modalSubmitActionLabel('Salvar')
                ->successNotificationTitle('Organização cadastrada')
                ->after(function (OrganizationRecord $record): void {
                    if (blank($record->cnpj)) {
                        return;
                    }

                    try {
                        app(SyncOrganizationRegistrationDataFromCnpjLookupUseCase::class)
                            ->execute(new SyncOrganizationRegistrationDataFromCnpjLookupCommand(
                                organizationId: $record->id,
                                cnpj: (string) $record->cnpj,
                            ));

                        $record->refresh();

                        Notification::make()
                            ->title('CNPJ sincronizado')
                            ->body('Endereço, contatos e dados cadastrais foram atualizados.')
                            ->success()
                            ->send();
                    } catch (Throwable $exception) {
                        report($exception);

                        Notification::make()
                            ->title('Organização cadastrada, mas o CNPJ não foi sincronizado')
                            ->body($exception->getMessage())
                            ->warning()
                            ->send();
                    }
                }),
        ];
    }
}
