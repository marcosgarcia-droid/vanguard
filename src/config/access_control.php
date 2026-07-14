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
     * Esta flag isoladamente nunca autoriza escrita.
     * O modo operacional também precisa permitir.
     */
    'writes_enabled' => env(
        'VANGUARD_ACCESS_CONTROL_WRITES_ENABLED',
        false
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
