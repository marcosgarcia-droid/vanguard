<?php

namespace App\Console\Commands;

use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class IssueIntelbrasAccessEventTokenCommand extends Command
{
    protected $signature =
        'vanguard:access-device:intelbras-event-token
        {device : UUID, código ou identificador externo do dispositivo}
        {--revoke : Revoga o token atual sem gerar outro}';

    protected $description =
        'Emite ou revoga o token de recepção passiva de eventos Intelbras.';

    public function handle(): int
    {
        $identifier = trim(
            (string) $this->argument('device')
        );

        $device = AccessDeviceRecord::query()
            ->where('id', $identifier)
            ->orWhere('code', $identifier)
            ->orWhere('external_id', $identifier)
            ->first();

        if (! $device instanceof AccessDeviceRecord) {
            $this->error(
                'Dispositivo de acesso não encontrado.'
            );

            return self::FAILURE;
        }

        if (
            strtolower(trim((string) $device->provider))
            !== 'intelbras'
        ) {
            $this->error(
                'O dispositivo informado não utiliza o provider Intelbras.'
            );

            return self::FAILURE;
        }

        $settings = is_array($device->settings)
            ? $device->settings
            : [];

        if ((bool) $this->option('revoke')) {
            data_forget(
                $settings,
                'intelbras_event_ingestion'
            );

            $device
                ->forceFill([
                    'settings' => $settings,
                ])
                ->saveQuietly();

            $this->info(
                'Token Intelbras revogado com sucesso.'
            );

            return self::SUCCESS;
        }

        $token = Str::random(64);

        data_set(
            $settings,
            'intelbras_event_ingestion',
            [
                'enabled' => true,
                'token_hash' => hash(
                    'sha256',
                    $token
                ),
                'issued_at' => now()->toIso8601String(),
            ]
        );

        $device
            ->forceFill([
                'settings' => $settings,
            ])
            ->saveQuietly();

        $url = route(
            'integrations.intelbras.access-events.store',
            [
                'device' => $device->id,
                'token' => $token,
            ]
        );

        $this->newLine();
        $this->info(
            'Token emitido com sucesso.'
        );
        $this->newLine();

        $this->line(
            'Configure o equipamento no modo POST Evento 2.0.'
        );
        $this->line(
            'Desative o envio de fotos.'
        );
        $this->line(
            'Selecione somente eventos de acesso.'
        );

        $this->newLine();
        $this->warn(
            'A URL abaixo contém o token e será exibida somente agora:'
        );
        $this->line($url);
        $this->newLine();

        if (
            ! (bool) config(
                'access_control.intelbras_post_events_enabled',
                false
            )
        ) {
            $this->warn(
                'A recepção global de eventos Intelbras ainda está desativada no ambiente.'
            );
        }

        return self::SUCCESS;
    }
}
