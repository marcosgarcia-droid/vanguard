<style>
    /*
     * Padronização visual do Vanguard:
     * - Listas/tabelas em maiúsculas
     * - Visualizações/infolists em maiúsculas
     * - Formulários continuam preservando a digitação original
     */
    .fi-ta-table tbody td,
    .fi-in .fi-in-entry-wrp,
    .fi-in .fi-in-text,
    .fi-in .fi-in-text-item-label,
    .fi-in .fi-badge,
    .fi-ta-table tbody .fi-badge {
        text-transform: uppercase;
    }

    /*
     * Evita afetar campos editáveis caso algum componente interativo
     * apareça dentro de tabela ou visualização.
     */
    .fi-ta-table tbody td input,
    .fi-ta-table tbody td textarea,
    .fi-ta-table tbody td select,
    .fi-in input,
    .fi-in textarea,
    .fi-in select {
        text-transform: none;
    }
</style>

<style>
    .vanguard-current-tenant-select {
        color-scheme: light;
    }

    .vanguard-current-tenant-select option {
        background-color: #ffffff;
        color: #111827;
    }

    .dark .vanguard-current-tenant-select {
        color-scheme: dark;
        background-color: #030712 !important;
        color: #ffffff !important;
    }

    .dark .vanguard-current-tenant-select option {
        background-color: #030712 !important;
        color: #ffffff !important;
    }

    .dark .vanguard-current-tenant-select option:checked,
    .dark .vanguard-current-tenant-select option:hover,
    .dark .vanguard-current-tenant-select option:focus {
        background-color: #f9fafb !important;
        color: #111827 !important;
    }
</style>
