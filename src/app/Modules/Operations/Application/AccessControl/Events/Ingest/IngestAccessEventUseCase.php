<?php

namespace App\Modules\Operations\Application\AccessControl\Events\Ingest;

use App\Modules\Operations\Domain\AccessControl\AccessDeviceStatus;
use App\Modules\Operations\Domain\AccessControl\AccessEventStatus;
use Throwable;

final readonly class IngestAccessEventUseCase
{
    public function __construct(
        private AccessEventIngestionRepository $repository,
        private AccessEventPayloadSanitizer $payloadSanitizer,
    ) {}

    public function execute(
        IngestAccessEventCommand $command
    ): IngestAccessEventResult {
        $deviceId = $this->requiredIdentifier(
            $command->accessDeviceId,
            'O identificador do dispositivo',
            36
        );

        $externalEventId = $this->requiredIdentifier(
            $command->externalEventId,
            'O identificador externo do evento',
            191
        );

        $externalPersonId = $this->optionalIdentifier(
            $command->externalPersonId,
            'O identificador externo da pessoa',
            255
        );

        $eventType = $this->requiredIdentifier(
            $command->eventType,
            'O tipo do evento',
            50
        );

        if ($eventType !== 'face_recognition') {
            throw new IngestAccessEventException(
                'O tipo de evento informado ainda não é suportado.'
            );
        }

        $target = $this->repository->findTarget(
            $deviceId
        );

        if ($target === null) {
            throw new IngestAccessEventException(
                'O dispositivo de acesso não foi encontrado.'
            );
        }

        $sanitizedPayload = $this->payloadSanitizer
            ->sanitize($command->payload);

        $this->assertProviderAllowed(
            $target->provider,
            $sanitizedPayload
        );

        if (
            $target->status
            !== AccessDeviceStatus::Active
        ) {
            throw new IngestAccessEventException(
                'O dispositivo precisa estar ativo para receber eventos.'
            );
        }

        if (
            ! $target->direction->accepts(
                $command->direction
            )
        ) {
            throw new IngestAccessEventException(
                'A direção do evento é incompatível com o dispositivo.'
            );
        }

        $status = $externalPersonId === null
            ? AccessEventStatus::Received
            : AccessEventStatus::PendingAssociation;

        $resultCode = $status
            === AccessEventStatus::PendingAssociation
                ? 'pending_association'
                : 'received';

        $resultMessage = $status
            === AccessEventStatus::PendingAssociation
                ? 'Evento recebido e aguardando associação com uma pessoa cadastrada.'
                : 'Evento técnico recebido sem referência externa de pessoa.';

        try {
            return $this->repository->persist(
                new AccessEventIngestionData(
                    deviceId: $target->deviceId,
                    externalEventId: $externalEventId,
                    externalPersonId: $externalPersonId,
                    eventType: $eventType,
                    direction: $command->direction,
                    occurredAt: $command->occurredAt,
                    status: $status,
                    resultCode: $resultCode,
                    resultMessage: $resultMessage,
                    payload: $sanitizedPayload,
                )
            );
        } catch (IngestAccessEventException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new IngestAccessEventException(
                message: 'Não foi possível registrar o evento de acesso.',
                previous: $exception,
            );
        }
    }

    /**
     * @param  array<string, bool|float|int|string>  $payload
     */
    private function assertProviderAllowed(
        string $provider,
        array $payload
    ): void {
        $provider = strtolower(
            trim($provider)
        );

        $source = strtolower(
            trim(
                (string) (
                    $payload['source']
                    ?? ''
                )
            )
        );

        if ($provider === 'simulator') {
            if (
                ! (bool) config(
                    'access_control.simulator_enabled',
                    false
                )
            ) {
                throw new IngestAccessEventException(
                    'O simulador de dispositivos está desativado neste ambiente.'
                );
            }

            if ($source !== 'simulator') {
                throw new IngestAccessEventException(
                    'Eventos de dispositivos simulados precisam identificar o simulador como origem.'
                );
            }

            return;
        }

        if ($source === 'simulator') {
            throw new IngestAccessEventException(
                'Eventos sintéticos só podem ser associados a dispositivos simulados.'
            );
        }
    }

    private function requiredIdentifier(
        string $value,
        string $label,
        int $maximumLength
    ): string {
        $value = trim($value);

        if ($value === '') {
            throw new IngestAccessEventException(
                $label.' é obrigatório.'
            );
        }

        if (mb_strlen($value) > $maximumLength) {
            throw new IngestAccessEventException(
                $label.' excede o tamanho permitido.'
            );
        }

        return $value;
    }

    private function optionalIdentifier(
        ?string $value,
        string $label,
        int $maximumLength
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (mb_strlen($value) > $maximumLength) {
            throw new IngestAccessEventException(
                $label.' excede o tamanho permitido.'
            );
        }

        return $value;
    }
}
