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
