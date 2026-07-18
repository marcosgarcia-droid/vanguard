<?php

namespace App\Modules\Operations\UI\Http\Controllers\Integrations\Intelbras;

use App\Http\Controllers\Controller;
use App\Modules\Operations\Application\AccessControl\Events\Ingest\IngestAccessEventException;
use App\Modules\Operations\Application\AccessControl\Events\Ingest\IngestAccessEventUseCase;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Events\IntelbrasAccessEventNormalizer;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Events\IntelbrasAccessEventReceiveException;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Events\IntelbrasAccessEventRequestAuthenticator;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

final class ReceiveIntelbrasAccessEventController extends Controller
{
    public function __construct(
        private readonly IntelbrasAccessEventRequestAuthenticator $authenticator,
        private readonly IntelbrasAccessEventNormalizer $normalizer,
        private readonly IngestAccessEventUseCase $ingestAccessEvent,
    ) {}

    public function __invoke(
        Request $request,
        string $device,
        string $token
    ): JsonResponse {
        if (
            ! (bool) config(
                'access_control.intelbras_post_events_enabled',
                false
            )
        ) {
            abort(404);
        }

        $accessDevice = AccessDeviceRecord::query()
            ->find($device);

        if (
            ! $accessDevice instanceof AccessDeviceRecord
            || ! $this->authenticator->authorize(
                $accessDevice,
                $token
            )
        ) {
            abort(404);
        }

        $maximumPayloadBytes = max(
            1024,
            (int) config(
                'access_control.intelbras_post_events_max_payload_bytes',
                262144
            )
        );

        $contentLength = (int) $request->server(
            'CONTENT_LENGTH',
            0
        );

        if (
            $contentLength > $maximumPayloadBytes
            || strlen($request->getContent())
                > $maximumPayloadBytes
        ) {
            $this->markCommunication(
                $accessDevice,
                'failed',
                'Evento rejeitado porque excedeu o tamanho permitido.'
            );

            return response()->json(
                [
                    'status' => 'rejected',
                    'message' => 'O evento excedeu o tamanho permitido.',
                ],
                413
            );
        }

        if (! $request->isJson()) {
            $this->markCommunication(
                $accessDevice,
                'failed',
                'Evento rejeitado porque não foi enviado em JSON.'
            );

            return response()->json(
                [
                    'status' => 'rejected',
                    'message' => 'O conteúdo precisa ser enviado em JSON.',
                ],
                415
            );
        }

        try {
            $payload = $request->json()->all();

            $command = $this->normalizer->normalize(
                $payload,
                $accessDevice
            );

            $result = $this->ingestAccessEvent->execute(
                $command
            );

            $this->markCommunication(
                $accessDevice,
                'success',
                $result->duplicate
                    ? 'Evento Intelbras duplicado reconhecido com segurança.'
                    : 'Evento Intelbras recebido com sucesso.'
            );

            return response()->json([
                'status' => $result->duplicate
                    ? 'duplicate'
                    : 'accepted',
            ]);
        } catch (
            IntelbrasAccessEventReceiveException
            |IngestAccessEventException $exception
        ) {
            $this->markCommunication(
                $accessDevice,
                'failed',
                $exception->getMessage()
            );

            return response()->json(
                [
                    'status' => 'rejected',
                    'message' => $exception->getMessage(),
                ],
                422
            );
        } catch (Throwable) {
            $this->markCommunication(
                $accessDevice,
                'failed',
                'Falha interna durante a recepção do evento Intelbras.'
            );

            return response()->json(
                [
                    'status' => 'error',
                    'message' => 'Não foi possível receber o evento.',
                ],
                500
            );
        }
    }

    private function markCommunication(
        AccessDeviceRecord $device,
        string $status,
        string $message
    ): void {
        $device
            ->forceFill([
                'last_communication_at' => now(),
                'last_communication_status' => $status,
                'last_communication_message' => mb_substr(
                    $message,
                    0,
                    500
                ),
            ])
            ->saveQuietly();
    }
}
