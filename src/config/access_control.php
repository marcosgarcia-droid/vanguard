<?php

return [

    /*
     * Modos disponíveis:
     *
     * observer  - somente leitura;
     * parallel  - comparação sem comando aos equipamentos;
     * primary   - VANGUARD como sistema principal;
     * emergency - contingência operacional Intelbras.
     */
    'mode' => env(
        'VANGUARD_ACCESS_CONTROL_MODE',
        'observer'
    ),

    /*
     * A comunicação de leitura permanece desativada até que seja
     * habilitada explicitamente no ambiente.
     */
    'reads_enabled' => env(
        'VANGUARD_ACCESS_CONTROL_READS_ENABLED',
        false
    ),

    /*
     * O simulador utiliza somente dados locais e sintéticos.
     * Nenhuma conexão HTTP ou acesso à rede é realizado.
     */
    'simulator_enabled' => env(
        'VANGUARD_ACCESS_CONTROL_SIMULATOR_ENABLED',
        false
    ),

    'simulator_default_scenario' => env(
        'VANGUARD_ACCESS_CONTROL_SIMULATOR_DEFAULT_SCENARIO',
        'success'
    ),

    /*
     * Redes IPv4 privadas autorizadas para os equipamentos.
     *
     * Em produção, informar somente a VLAN ou sub-redes exatas dos
     * dispositivos, separadas por vírgula.
     */
    'allowed_cidrs' => array_values(
        array_filter(
            array_map(
                'trim',
                explode(
                    ',',
                    (string) env(
                        'VANGUARD_ACCESS_CONTROL_ALLOWED_CIDRS',
                        ''
                    )
                )
            )
        )
    ),

    /*
     * Esta flag isoladamente nunca autoriza escrita.
     * O modo operacional também precisa permitir.
     */
    'writes_enabled' => env(
        'VANGUARD_ACCESS_CONTROL_WRITES_ENABLED',
        false
    ),

    /*
     * Autoriza apenas a futura execução automática de operações
     * internas de entrada e saída de visitas.
     *
     * A flag permanece bloqueada pelo runtime fora do modo primary.
     * Ela não autoriza escrita ou comando nos equipamentos.
     */
    'automatic_visit_operations_enabled' => env(
        'VANGUARD_ACCESS_CONTROL_AUTOMATIC_VISIT_OPERATIONS_ENABLED',
        false
    ),

    /*
     * Atualização automática somente da listagem de eventos.
     *
     * Esta configuração não ingere, processa ou reprocessa eventos.
     * Também não realiza comunicação com dispositivos.
     */
    'event_list_polling_enabled' => env(
        'VANGUARD_ACCESS_CONTROL_EVENT_LIST_POLLING_ENABLED',
        false
    ),

    /*
     * O intervalo efetivo é limitado pela interface entre
     * 30 e 300 segundos.
     */
    'event_list_polling_interval_seconds' => (int) env(
        'VANGUARD_ACCESS_CONTROL_EVENT_LIST_POLLING_INTERVAL_SECONDS',
        30
    ),
    /*
     * Uma única leitura pode ser executada por dispositivo.
     *
     * O guard aplica um piso seguro de 60 segundos ao lock,
     * mesmo que o ambiente informe um valor menor.
     */
    'read_lock_seconds' => (int) env(
        'VANGUARD_ACCESS_CONTROL_READ_LOCK_SECONDS',
        60
    ),

    /*
     * Intervalo mínimo entre chamadas efetivas ao reader para o
     * mesmo dispositivo. Zero desativa apenas o intervalo;
     * o lock de concorrência permanece obrigatório.
     */
    'read_min_interval_seconds' => (int) env(
        'VANGUARD_ACCESS_CONTROL_READ_MIN_INTERVAL_SECONDS',
        30
    ),

    /*
     * Limites conservadores para futuras consultas aos equipamentos.
     */
    'connect_timeout_seconds' => (int) env(
        'VANGUARD_ACCESS_CONTROL_CONNECT_TIMEOUT',
        2
    ),

    'request_timeout_seconds' => (int) env(
        'VANGUARD_ACCESS_CONTROL_REQUEST_TIMEOUT',
        5
    ),

    /*
     * Recepção passiva de eventos enviados pelos dispositivos Intelbras.
     *
     * Esta flag não habilita modo online, decisão remota, abertura de porta,
     * sincronização de pessoas ou qualquer escrita nos equipamentos.
     */
    'intelbras_post_events_enabled' => env(
        'VANGUARD_ACCESS_CONTROL_INTELBRAS_POST_EVENTS_ENABLED',
        false
    ),

    /*
     * Limite do corpo JSON recebido.
     *
     * O envio de imagens deve permanecer desativado no equipamento.
     */
    'intelbras_post_events_max_payload_bytes' => (int) env(
        'VANGUARD_ACCESS_CONTROL_INTELBRAS_POST_EVENTS_MAX_PAYLOAD_BYTES',
        262144
    ),

];
