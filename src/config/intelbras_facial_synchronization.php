<?php

declare(strict_types=1);

$simulatorScenario = env(
    'VANGUARD_INTELBRAS_FACIAL_SYNCHRONIZATION_SIMULATOR_SCENARIO'
);

if (is_string($simulatorScenario)) {
    $simulatorScenario = trim($simulatorScenario);
}

if (
    ! is_string($simulatorScenario)
    || $simulatorScenario === ''
) {
    $simulatorScenario = null;
}

return [

    /*
    |--------------------------------------------------------------------------
    | Sincronização de credenciais faciais Intelbras
    |--------------------------------------------------------------------------
    |
    | Nenhum sincronizador físico é selecionado por padrão.
    |
    | Os únicos providers reconhecidos nesta etapa são:
    |
    | disabled  - bloqueia qualquer sincronização;
    | simulator - produz somente respostas sintéticas locais.
    |
    | O valor intelbras ainda não é suportado e permanece bloqueado.
    |
    */

    'provider' => env(
        'VANGUARD_INTELBRAS_FACIAL_SYNCHRONIZATION_PROVIDER',
        'disabled'
    ),

    /*
    |--------------------------------------------------------------------------
    | Simulador local
    |--------------------------------------------------------------------------
    |
    | O simulador não realiza HTTP, autenticação, acesso à rede,
    | persistência, filas ou comunicação com equipamentos.
    |
    */

    'simulator' => [
        'enabled' => env(
            'VANGUARD_INTELBRAS_FACIAL_SYNCHRONIZATION_SIMULATOR_ENABLED',
            false
        ),

        /*
         * Esta lista não é controlada por variável de ambiente.
         * O simulador nunca poderá ser liberado em produção por .env.
         */
        'allowed_environments' => [
            'local',
            'testing',
        ],

        /*
         * Nenhum cenário é escolhido implicitamente.
         * A simulação exige um cenário sintético explícito e válido.
         */
        'scenario' => $simulatorScenario,
    ],

];
