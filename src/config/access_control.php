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

];
