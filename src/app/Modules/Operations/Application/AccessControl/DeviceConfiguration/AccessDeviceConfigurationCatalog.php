<?php

namespace App\Modules\Operations\Application\AccessControl\DeviceConfiguration;

use App\Modules\Operations\Domain\AccessControl\AccessDeviceConfigurationOperation;

final class AccessDeviceConfigurationCatalog
{
    /**
     * @return array<string, array{
     *     label: string,
     *     description: string
     * }>
     */
    public static function categories(): array
    {
        return [
            'device' => [
                'label' => 'Dispositivo',
                'description' => 'Parâmetros gerais, interface, firmware e comandos administrativos.',
            ],
            'turnstile' => [
                'label' => 'Modo catraca',
                'description' => 'Configurações de direção e confirmação de passagem.',
            ],
            'door' => [
                'label' => 'Porta, sensor e relé',
                'description' => 'Parâmetros físicos da porta, sensores, relés e tempos de acionamento.',
            ],
            'alarms' => [
                'label' => 'Alarmes',
                'description' => 'Alarmes de porta, arrombamento, antipassback e saídas de alarme.',
            ],
            'wiegand' => [
                'label' => 'Wiegand',
                'description' => 'Configuração da comunicação e saída Wiegand.',
            ],
            'cards' => [
                'label' => 'Leitura de cartões',
                'description' => 'Parâmetros relacionados a cartões e leitores auxiliares.',
            ],
            'face' => [
                'label' => 'Reconhecimento facial',
                'description' => 'Qualidade, distância, exposição e sensibilidade do reconhecimento.',
            ],
            'network' => [
                'label' => 'Rede',
                'description' => 'Informações de rede disponíveis para consulta controlada.',
            ],
        ];
    }

    /**
     * @return array<int, array{
     *     key: string,
     *     category: string,
     *     label: string,
     *     operation: AccessDeviceConfigurationOperation,
     *     description: string
     * }>
     */
    public static function definitions(): array
    {
        return [
            self::definition(
                'device.date_time',
                'device',
                'Data e hora do dispositivo',
                AccessDeviceConfigurationOperation::Status,
                'Data e hora configuradas no equipamento.'
            ),
            self::definition(
                'device.firmware_version',
                'device',
                'Versão do firmware',
                AccessDeviceConfigurationOperation::Status,
                'Versão de firmware informada pelo equipamento.'
            ),
            self::definition(
                'device.interface_buttons_hidden',
                'device',
                'Ocultar todos os botões da interface',
                AccessDeviceConfigurationOperation::Configuration,
                'Controla a exibição dos botões na interface local.'
            ),
            self::definition(
                'device.usb_disabled',
                'device',
                'Desativar porta USB',
                AccessDeviceConfigurationOperation::Configuration,
                'Controla a disponibilidade da porta USB.'
            ),
            self::definition(
                'device.restart',
                'device',
                'Reiniciar dispositivo',
                AccessDeviceConfigurationOperation::Command,
                'Reinicialização remota do equipamento.'
            ),
            self::definition(
                'device.factory_reset',
                'device',
                'Restaurar configurações de fábrica',
                AccessDeviceConfigurationOperation::Command,
                'Restauração remota das configurações do equipamento.'
            ),

            self::definition(
                'turnstile.mode_enabled',
                'turnstile',
                'Modo catraca',
                AccessDeviceConfigurationOperation::Configuration,
                'Ativa a integração específica com a catraca.'
            ),
            self::definition(
                'turnstile.direction',
                'turnstile',
                'Direção da catraca',
                AccessDeviceConfigurationOperation::Configuration,
                'Define se o equipamento trabalha na entrada ou na saída.'
            ),
            self::definition(
                'turnstile.extra_reader_direction',
                'turnstile',
                'Direção do leitor auxiliar',
                AccessDeviceConfigurationOperation::Configuration,
                'Define a direção associada ao leitor auxiliar.'
            ),
            self::definition(
                'turnstile.pass_confirmation_timeout_seconds',
                'turnstile',
                'Tempo de confirmação de passagem',
                AccessDeviceConfigurationOperation::Configuration,
                'Tempo esperado para a confirmação física da passagem.'
            ),

            self::definition(
                'door.current_status',
                'door',
                'Status atual da porta',
                AccessDeviceConfigurationOperation::Status,
                'Estado físico atualmente informado pelo equipamento.'
            ),
            self::definition(
                'door.sensor_enabled',
                'door',
                'Sensor de porta',
                AccessDeviceConfigurationOperation::Configuration,
                'Habilita ou desabilita o sensor físico da porta.'
            ),
            self::definition(
                'door.sensor_state',
                'door',
                'Estado do sensor',
                AccessDeviceConfigurationOperation::Configuration,
                'Configura o comportamento ou estado lógico do sensor.'
            ),
            self::definition(
                'door.sensor_delay_seconds',
                'door',
                'Atraso do sensor de porta',
                AccessDeviceConfigurationOperation::Configuration,
                'Tempo de atraso usado pelo sensor de porta.'
            ),
            self::definition(
                'door.relay_state',
                'door',
                'Estado do relé',
                AccessDeviceConfigurationOperation::Configuration,
                'Configuração lógica do relé de acionamento.'
            ),
            self::definition(
                'door.relay_activation_seconds',
                'door',
                'Tempo de acionamento do relé',
                AccessDeviceConfigurationOperation::Configuration,
                'Tempo durante o qual o relé permanece acionado.'
            ),
            self::definition(
                'door.exit_button_enabled',
                'door',
                'Botão de saída',
                AccessDeviceConfigurationOperation::Configuration,
                'Habilita ou desabilita o botão físico de saída.'
            ),
            self::definition(
                'door.verification_method',
                'door',
                'Método de verificação',
                AccessDeviceConfigurationOperation::Configuration,
                'Define o método de credencial necessário para liberação.'
            ),
            self::definition(
                'door.open_time_zone',
                'door',
                'Zona de tempo aberta ou fechada',
                AccessDeviceConfigurationOperation::Configuration,
                'Define a zona de tempo usada para o comportamento da porta.'
            ),
            self::definition(
                'door.open_close',
                'door',
                'Abrir ou fechar porta',
                AccessDeviceConfigurationOperation::Command,
                'Comando físico imediato para a porta ou catraca.'
            ),
            self::definition(
                'door.change_state',
                'door',
                'Alterar estado da porta',
                AccessDeviceConfigurationOperation::Command,
                'Altera imediatamente o estado operacional da porta.'
            ),

            self::definition(
                'alarms.door_open_enabled',
                'alarms',
                'Alarme de porta aberta',
                AccessDeviceConfigurationOperation::Configuration,
                'Gera alarme quando a porta permanece aberta além do tempo permitido.'
            ),
            self::definition(
                'alarms.break_in_enabled',
                'alarms',
                'Alarme de arrombamento',
                AccessDeviceConfigurationOperation::Configuration,
                'Gera alarme quando uma abertura não autorizada é detectada.'
            ),
            self::definition(
                'alarms.antipassback_enabled',
                'alarms',
                'Antipassback',
                AccessDeviceConfigurationOperation::Configuration,
                'Impede sequências incompatíveis de entrada e saída.'
            ),
            self::definition(
                'alarms.duress_enabled',
                'alarms',
                'Alarme de coação',
                AccessDeviceConfigurationOperation::Configuration,
                'Configuração do evento de coação.'
            ),
            self::definition(
                'alarms.output_enabled',
                'alarms',
                'Saída de alarme',
                AccessDeviceConfigurationOperation::Configuration,
                'Habilita ou desabilita a saída física de alarme.'
            ),
            self::definition(
                'alarms.output_status',
                'alarms',
                'Status da saída de alarme',
                AccessDeviceConfigurationOperation::Status,
                'Estado atual da saída de alarme.'
            ),
            self::definition(
                'alarms.activate_output',
                'alarms',
                'Acionar saída de alarme',
                AccessDeviceConfigurationOperation::Command,
                'Acionamento físico imediato da saída de alarme.'
            ),
            self::definition(
                'alarms.cancel_current_sound',
                'alarms',
                'Cancelar alarme sonoro',
                AccessDeviceConfigurationOperation::Command,
                'Interrompe o alarme sonoro corrente.'
            ),

            self::definition(
                'wiegand.mode',
                'wiegand',
                'Modo Wiegand',
                AccessDeviceConfigurationOperation::Configuration,
                'Modo operacional da interface Wiegand.'
            ),
            self::definition(
                'wiegand.pulse_width',
                'wiegand',
                'Largura de pulso',
                AccessDeviceConfigurationOperation::Configuration,
                'Largura dos pulsos da comunicação Wiegand.'
            ),
            self::definition(
                'wiegand.pulse_step',
                'wiegand',
                'Intervalo de pulso',
                AccessDeviceConfigurationOperation::Configuration,
                'Intervalo entre os pulsos da comunicação Wiegand.'
            ),
            self::definition(
                'wiegand.transfer_mode',
                'wiegand',
                'Modo de transferência',
                AccessDeviceConfigurationOperation::Configuration,
                'Modo usado para transmitir os dados Wiegand.'
            ),
            self::definition(
                'wiegand.output_type',
                'wiegand',
                'Tipo de saída',
                AccessDeviceConfigurationOperation::Configuration,
                'Formato ou tipo da saída Wiegand.'
            ),

            self::definition(
                'cards.reader_configuration',
                'cards',
                'Configuração da leitura de cartões',
                AccessDeviceConfigurationOperation::Configuration,
                'Parâmetros de funcionamento do leitor de cartões.'
            ),
            self::definition(
                'cards.auxiliary_reader_enabled',
                'cards',
                'Leitor auxiliar',
                AccessDeviceConfigurationOperation::Configuration,
                'Habilita e configura o leitor auxiliar.'
            ),

            self::definition(
                'face.similarity',
                'face',
                'Similaridade',
                AccessDeviceConfigurationOperation::Configuration,
                'Limiar mínimo de similaridade para reconhecimento.'
            ),
            self::definition(
                'face.maximum_angle',
                'face',
                'Ângulo máximo de reconhecimento',
                AccessDeviceConfigurationOperation::Configuration,
                'Ângulo máximo aceito para reconhecimento facial.'
            ),
            self::definition(
                'face.maximum_distance',
                'face',
                'Distância máxima de reconhecimento',
                AccessDeviceConfigurationOperation::Configuration,
                'Distância máxima aceita entre a pessoa e o equipamento.'
            ),
            self::definition(
                'face.target_brightness',
                'face',
                'Brilho alvo',
                AccessDeviceConfigurationOperation::Configuration,
                'Brilho desejado para a captura facial.'
            ),
            self::definition(
                'face.pupillary_distance',
                'face',
                'Distância pupilar',
                AccessDeviceConfigurationOperation::Configuration,
                'Parâmetro mínimo ou máximo de distância pupilar.'
            ),
            self::definition(
                'face.exposure',
                'face',
                'Exposição facial',
                AccessDeviceConfigurationOperation::Configuration,
                'Parâmetros de exposição usados na captura facial.'
            ),
            self::definition(
                'face.infrared_light',
                'face',
                'Luz infravermelha',
                AccessDeviceConfigurationOperation::Configuration,
                'Configuração da iluminação infravermelha.'
            ),
            self::definition(
                'face.mask_mode',
                'face',
                'Modo de máscara',
                AccessDeviceConfigurationOperation::Configuration,
                'Comportamento do reconhecimento para pessoas com máscara.'
            ),
            self::definition(
                'face.recognition_timeout_seconds',
                'face',
                'Tempo limite para reconhecimento',
                AccessDeviceConfigurationOperation::Configuration,
                'Tempo máximo usado na tentativa de reconhecimento.'
            ),
            self::definition(
                'face.capture_image',
                'face',
                'Obter captura de imagem',
                AccessDeviceConfigurationOperation::Command,
                'Solicita uma captura remota da câmera do equipamento.'
            ),

            self::definition(
                'network.ip_address',
                'network',
                'Endereço IP detectado',
                AccessDeviceConfigurationOperation::Status,
                'Endereço IP atualmente informado pelo dispositivo.'
            ),
            self::definition(
                'network.subnet_mask',
                'network',
                'Máscara de rede',
                AccessDeviceConfigurationOperation::Status,
                'Máscara de rede atualmente configurada.'
            ),
            self::definition(
                'network.default_gateway',
                'network',
                'Gateway padrão',
                AccessDeviceConfigurationOperation::Status,
                'Gateway padrão atualmente configurado.'
            ),
        ];
    }

    /**
     * @return array<string, array{
     *     label: string,
     *     description: string,
     *     items: array<int, array{
     *         key: string,
     *         category: string,
     *         label: string,
     *         operation: AccessDeviceConfigurationOperation,
     *         description: string
     *     }>
     * }>
     */
    public static function grouped(): array
    {
        $definitions = collect(self::definitions())
            ->groupBy('category');

        return collect(self::categories())
            ->mapWithKeys(
                fn (array $category, string $key): array => [
                    $key => array_merge($category, [
                        'items' => $definitions
                            ->get($key, collect())
                            ->values()
                            ->all(),
                    ]),
                ]
            )
            ->all();
    }

    /**
     * @return array{
     *     key: string,
     *     category: string,
     *     label: string,
     *     operation: AccessDeviceConfigurationOperation,
     *     description: string
     * }|null
     */
    public static function find(string $key): ?array
    {
        return collect(self::definitions())
            ->firstWhere('key', $key);
    }

    /**
     * @return array{
     *     key: string,
     *     category: string,
     *     label: string,
     *     operation: AccessDeviceConfigurationOperation,
     *     description: string
     * }
     */
    private static function definition(
        string $key,
        string $category,
        string $label,
        AccessDeviceConfigurationOperation $operation,
        string $description
    ): array {
        return [
            'key' => $key,
            'category' => $category,
            'label' => $label,
            'operation' => $operation,
            'description' => $description,
        ];
    }
}
