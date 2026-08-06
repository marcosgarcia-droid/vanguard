<?php

return [
    'timeline' => 'Histórico',

    'action' => [
        'timeline' => [
            'label' => 'Histórico',
            'empty_state_title' => 'Nenhum histórico encontrado',
            'empty_state_description' => 'Este registro ainda não possui alterações registradas.',
        ],
    ],

    'event' => [
        'created' => 'Criado',
        'updated' => 'Atualizado',
        'deleted' => 'Excluído',
        'restored' => 'Restaurado',

        'visitor_facial_credential_synchronization_created' => 'Intenção de sincronização facial criada',

        'visitor_facial_credential_synchronization_reused' => 'Intenção de sincronização facial reutilizada',

        'visitor_facial_credential_synchronization_blocked' => 'Preparação da sincronização facial bloqueada',

        'visitor_facial_credential_synchronization_failed' => 'Falha ao preparar sincronização facial',
    ],

    'table' => [
        'column' => [
            'event' => 'Evento',
            'risk' => 'Risco',
            'subject' => 'Registro',
            'causer' => 'Usuário',
            'ip_address' => 'Endereço IP',
            'browser' => 'Navegador',
            'description' => 'Descrição',
            'created_at' => 'Data/hora',
        ],
    ],

    'filters' => 'Filtros',

    'infolist' => [
        'tab' => [
            'overview' => 'Visão geral',
            'changes' => 'Alterações',
            'properties' => 'Propriedades',
        ],
    ],
];
