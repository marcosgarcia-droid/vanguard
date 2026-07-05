<?php

namespace App\Modules\Identity\UI\Filament\Resources\OrganizationRecords\Actions;

use App\Modules\Identity\Application\Organizations\RegistrationData\SyncOrganizationRegistrationDataFromCnpjLookup\SyncOrganizationRegistrationDataFromCnpjLookupCommand;
use App\Modules\Identity\Application\Organizations\RegistrationData\SyncOrganizationRegistrationDataFromCnpjLookup\SyncOrganizationRegistrationDataFromCnpjLookupUseCase;
use App\Modules\Identity\Domain\Organizations\ValueObjects\Cnpj;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\UI\Filament\Resources\OrganizationRecords\Rules\ValidOrganizationCnpjCorrection;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Throwable;

final class CorrectOrganizationCnpjAction
{
    public static function make(
        string $name = 'correctOrganizationCnpj',
        bool $iconButton = true,
    ): Action {
        $action = Action::make($name)
            ->label('Corrigir CNPJ')
            ->tooltip('Corrigir CNPJ')
            ->icon('heroicon-o-pencil-square')
            ->color('warning')
            ->modalHeading(fn (OrganizationRecord $record): string => 'Corrigir CNPJ - '.$record->operational_name)
            ->modalDescription('Use esta ação somente quando o CNPJ da organização foi cadastrado incorretamente. A correção sincronizará novamente os dados fiscais, preservando os dados operacionais cadastrados manualmente.')
            ->modalSubmitActionLabel('Corrigir CNPJ')
            ->form([
                TextInput::make('current_cnpj')
                    ->label('CNPJ atual')
                    ->default(fn (OrganizationRecord $record): string => self::formatCnpj((string) $record->cnpj))
                    ->disabled()
                    ->dehydrated(false)
                    ->columnSpanFull(),

                TextInput::make('new_cnpj')
                    ->label('Novo CNPJ')
                    ->placeholder('00.000.000/0000-00')
                    ->mask('99.999.999/9999-99')
                    ->helperText('Após confirmar, os dados fiscais serão sincronizados novamente. Dados operacionais serão preservados.')
                    ->required()
                    ->rules([
                        fn (OrganizationRecord $record): ValidOrganizationCnpjCorrection => new ValidOrganizationCnpjCorrection($record->id),
                    ])
                    ->maxLength(18)
                    ->columnSpanFull(),
            ])
            ->action(function (OrganizationRecord $record, array $data): void {
                $cnpj = self::cnpjFromValue($data['new_cnpj'] ?? null);

                if ($cnpj === null) {
                    Notification::make()
                        ->title('CNPJ inválido')
                        ->body('Informe um CNPJ válido antes de confirmar a correção.')
                        ->danger()
                        ->send();

                    return;
                }

                if ($cnpj->value() === (string) $record->cnpj) {
                    Notification::make()
                        ->title('CNPJ não alterado')
                        ->body('O novo CNPJ informado é igual ao CNPJ atual da organização.')
                        ->warning()
                        ->send();

                    return;
                }

                if (self::cnpjExistsForAnotherOrganization($cnpj, $record)) {
                    Notification::make()
                        ->title('CNPJ já cadastrado')
                        ->body('Este CNPJ já está cadastrado em outra organização.')
                        ->danger()
                        ->send();

                    return;
                }

                try {
                    app(SyncOrganizationRegistrationDataFromCnpjLookupUseCase::class)
                        ->execute(new SyncOrganizationRegistrationDataFromCnpjLookupCommand(
                            organizationId: $record->id,
                            cnpj: $cnpj->value(),
                        ));

                    $record->refresh();

                    Notification::make()
                        ->title('CNPJ corrigido')
                        ->body('O CNPJ foi corrigido e os dados fiscais foram sincronizados. Dados operacionais foram preservados.')
                        ->success()
                        ->send();
                } catch (Throwable $exception) {
                    report($exception);

                    Notification::make()
                        ->title('Não foi possível corrigir o CNPJ')
                        ->body('Não conseguimos consultar os serviços de CNPJ agora. Isso pode acontecer por instabilidade ou limite temporário das APIs gratuitas. Tente novamente mais tarde.')
                        ->danger()
                        ->send();
                }
            });

        return $iconButton
            ? $action->iconButton()
            : $action;
    }

    private static function cnpjFromValue(mixed $value): ?Cnpj
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        try {
            return new Cnpj($digits);
        } catch (Throwable) {
            return null;
        }
    }

    private static function cnpjExistsForAnotherOrganization(Cnpj $cnpj, OrganizationRecord $record): bool
    {
        return DB::table('organizations')
            ->where('cnpj', $cnpj->value())
            ->where('id', '!=', $record->id)
            ->exists();
    }

    private static function formatCnpj(?string $value): string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        if (strlen($digits) !== 14) {
            return $value ?: '-';
        }

        return sprintf(
            '%s.%s.%s/%s-%s',
            substr($digits, 0, 2),
            substr($digits, 2, 3),
            substr($digits, 5, 3),
            substr($digits, 8, 4),
            substr($digits, 12, 2),
        );
    }
}
