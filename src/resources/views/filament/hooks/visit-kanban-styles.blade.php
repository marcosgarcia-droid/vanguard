<style>
    /*
     * Kanban operacional de Visitas.
     * Estilos isolados para não depender da compilação Tailwind
     * das views personalizadas.
     */
    .fi-kanban-board,
    .fi-kanban-board * {
        box-sizing: border-box;
    }

    .fi-kanban-board {
        width: 100%;
    }

    .fi-kanban-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 1rem;
    }

    .fi-kanban-search {
        position: relative;
        width: 100%;
        max-width: 30rem;
    }

    .fi-kanban-search > div {
        position: relative;
    }

    .fi-kanban-search-input {
        display: block;
        width: 100%;
        min-height: 2.5rem;
        border: 0;
        border-radius: 0.5rem;
        background: #ffffff;
        padding: 0.625rem 0.75rem 0.625rem 2.5rem;
        color: #111827;
        font-size: 0.875rem;
        box-shadow:
            0 1px 2px rgb(0 0 0 / 0.05),
            0 0 0 1px rgb(17 24 39 / 0.10);
    }

    .fi-kanban-search-input:focus {
        outline: 2px solid #d97706;
        outline-offset: 1px;
    }

    .fi-kanban-filter {
        position: relative;
    }

    .fi-kanban-filter-popover {
        position: absolute;
        z-index: 40;
        width: 20rem;
        margin-top: 0.5rem;
        border-radius: 0.75rem;
        background: #ffffff;
        padding: 1rem;
        box-shadow:
            0 20px 25px -5px rgb(0 0 0 / 0.10),
            0 8px 10px -6px rgb(0 0 0 / 0.10);
        border: 1px solid #e5e7eb;
    }

    .fi-kanban-notice {
        display: flex;
        align-items: flex-start;
        gap: 0.5rem;
        margin-bottom: 1rem;
        border: 1px solid #bae6fd;
        border-radius: 0.75rem;
        background: #f0f9ff;
        padding: 0.75rem;
        color: #0369a1;
        font-size: 0.875rem;
    }

    .fi-kanban-loading {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.75rem;
        color: #6b7280;
        font-size: 0.875rem;
    }

    .fi-kanban-columns {
        display: flex;
        align-items: flex-start;
        gap: 1rem;
        width: 100%;
        min-height: 60vh;
        overflow-x: auto;
        padding: 0.25rem 0.125rem 1rem;
        scrollbar-width: thin;
    }

    .fi-kanban-column {
        display: flex;
        flex: 0 0 340px;
        width: 340px !important;
        height: clamp(24rem, calc(100vh - 17rem), 48rem);
        min-height: 0;
        flex-direction: column;
        overflow: hidden;
        border: 1px solid #e5e7eb;
        border-top-width: 3px;
        border-radius: 0.75rem;
        background: #f9fafb;
    }

    .fi-kanban-column:nth-child(1) {
        border-top-color: #0ea5e9;
    }

    .fi-kanban-column:nth-child(2) {
        border-top-color: #f59e0b;
    }

    .fi-kanban-column:nth-child(3) {
        border-top-color: #22c55e;
    }

    .fi-kanban-column:nth-child(4) {
        border-top-color: #d97706;
    }

    .fi-kanban-column:nth-child(5) {
        border-top-color: #16a34a;
    }

    .fi-kanban-column-header {
        display: flex;
        flex: 0 0 auto;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        min-height: 3.25rem;
        padding: 0.75rem;
        border-bottom: 1px solid #e5e7eb;
        background: rgb(255 255 255 / 0.75);
    }

    .fi-kanban-column-title {
        min-width: 0;
        overflow: hidden;
        color: #111827;
        font-size: 0.875rem;
        font-weight: 700;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .fi-kanban-column-count {
        display: inline-flex;
        min-width: 1.75rem;
        align-items: center;
        justify-content: center;
        border-radius: 9999px;
        background: #e5e7eb;
        padding: 0.125rem 0.5rem;
        color: #374151;
        font-size: 0.75rem;
        font-weight: 700;
    }

    .fi-kanban-column-records {
        min-height: 0;
        flex: 1 1 auto;
        overflow-y: auto;
        overscroll-behavior: contain;
        padding: 0.5rem;
        scrollbar-gutter: stable;
        scrollbar-width: thin;
    }

    .fi-kanban-column-records > * + * {
        margin-top: 0.75rem;
    }

    .fi-kanban-column-empty {
        display: flex;
        min-height: 7rem;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 1.5rem 0.75rem;
        text-align: center;
    }

    .fi-kanban-card {
        overflow: hidden;
        border: 1px solid #e5e7eb;
        border-radius: 0.75rem;
        background: #ffffff;
        box-shadow: 0 1px 2px rgb(0 0 0 / 0.05);
        transition:
            box-shadow 150ms ease,
            transform 150ms ease;
    }

    .fi-kanban-card:hover {
        box-shadow:
            0 4px 6px -1px rgb(0 0 0 / 0.08),
            0 2px 4px -2px rgb(0 0 0 / 0.08);
        transform: translateY(-1px);
    }

    .fi-kanban-card-header {
        display: flex;
        gap: 0.75rem;
        padding: 0.75rem;
    }

    .fi-kanban-card-photo-wrap {
        flex: 0 0 auto;
    }

    .fi-kanban-card-photo,
    .fi-kanban-card-initials {
        width: 5rem;
        height: 6rem;
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
    }

    .fi-kanban-card-photo {
        display: block;
        object-fit: cover;
    }

    .fi-kanban-card-initials {
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f3f4f6;
        color: #6b7280;
        font-size: 1.25rem;
        font-weight: 700;
    }

    .fi-kanban-card-summary {
        min-width: 0;
        flex: 1 1 auto;
    }

    .fi-kanban-card-title-row {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.5rem;
    }

    .fi-kanban-card-name {
        color: #111827;
        font-size: 0.875rem;
        font-weight: 700;
        line-height: 1.25rem;
        overflow-wrap: anywhere;
        white-space: normal;
    }

    .fi-kanban-card-official-name {
        margin-top: 0.125rem;
        color: #6b7280;
        font-size: 0.75rem;
        line-height: 1rem;
        overflow-wrap: anywhere;
        white-space: normal;
    }

    .fi-kanban-card-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 0.25rem;
        margin-top: 0.5rem;
    }

    .fi-kanban-badge {
        display: inline-flex;
        align-items: center;
        border-radius: 0.375rem;
        padding: 0.125rem 0.375rem;
        font-size: 0.6875rem;
        font-weight: 700;
        line-height: 1rem;
    }

    .fi-kanban-badge.bg-info-50 {
        background: #f0f9ff;
        color: #0369a1;
    }

    .fi-kanban-badge.bg-warning-50 {
        background: #fffbeb;
        color: #b45309;
    }

    .fi-kanban-badge.bg-success-50 {
        background: #f0fdf4;
        color: #15803d;
    }

    .fi-kanban-badge.bg-primary-50 {
        background: #fffbeb;
        color: #b45309;
    }

    .fi-kanban-badge.bg-gray-50 {
        background: #f3f4f6;
        color: #4b5563;
    }

    .fi-kanban-card-details {
        margin: 0;
        padding: 0.75rem;
        border-top: 1px solid #f3f4f6;
        font-size: 0.75rem;
    }

    .fi-kanban-card-detail {
        display: flex;
        align-items: flex-start;
        gap: 0.5rem;
    }

    .fi-kanban-card-detail + .fi-kanban-card-detail {
        margin-top: 0.5rem;
    }

    .fi-kanban-card-label {
        flex: 0 0 5rem;
        width: 5rem;
        color: #6b7280;
        font-weight: 600;
    }

    .fi-kanban-card-value {
        min-width: 0;
        color: #374151;
        overflow-wrap: anywhere;
    }

    .fi-kanban-card-actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: flex-end;
        gap: 0.25rem;
        min-height: 2.75rem;
        padding: 0.5rem 0.75rem;
        border-top: 1px solid #f3f4f6;
    }

    .dark .fi-kanban-search-input,
    .dark .fi-kanban-filter-popover,
    .dark .fi-kanban-card {
        background: #111827;
        color: #ffffff;
        border-color: rgb(255 255 255 / 0.10);
    }

    .dark .fi-kanban-search-input {
        box-shadow: 0 0 0 1px rgb(255 255 255 / 0.15);
    }

    .dark .fi-kanban-notice {
        border-color: rgb(56 189 248 / 0.25);
        background: rgb(14 165 233 / 0.10);
        color: #7dd3fc;
    }

    .dark .fi-kanban-column {
        border-color: rgb(255 255 255 / 0.10);
        background: rgb(255 255 255 / 0.04);
    }

    .dark .fi-kanban-column-header {
        border-bottom-color: rgb(255 255 255 / 0.08);
        background: rgb(17 24 39 / 0.75);
    }

    .dark .fi-kanban-column-title,
    .dark .fi-kanban-card-name {
        color: #ffffff;
    }

    .dark .fi-kanban-column-count {
        background: rgb(255 255 255 / 0.10);
        color: #d1d5db;
    }

    .dark .fi-kanban-card-photo,
    .dark .fi-kanban-card-initials {
        border-color: rgb(255 255 255 / 0.10);
    }

    .dark .fi-kanban-card-initials {
        background: rgb(255 255 255 / 0.06);
        color: #9ca3af;
    }

    .dark .fi-kanban-card-official-name,
    .dark .fi-kanban-card-label {
        color: #9ca3af;
    }

    .dark .fi-kanban-card-value {
        color: #e5e7eb;
    }

    .dark .fi-kanban-card-details,
    .dark .fi-kanban-card-actions {
        border-color: rgb(255 255 255 / 0.08);
    }

    @media (max-width: 640px) {
        .fi-kanban-column {
            flex-basis: min(340px, calc(100vw - 3rem));
            width: min(340px, calc(100vw - 3rem)) !important;
        }

        .fi-kanban-filter-popover {
            width: min(20rem, calc(100vw - 3rem));
        }
    }
</style>

<style>
    /* Barra de busca operacional do Kanban */
    .fi-kanban-toolbar {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        align-items: center;
        gap: 0.5rem;
        width: 100%;
        margin-bottom: 0.75rem;
        padding: 0.5rem;
        border: 1px solid #e5e7eb;
        border-radius: 0.75rem;
        background: #ffffff;
        box-shadow: 0 1px 2px rgb(0 0 0 / 0.04);
    }

    .fi-kanban-search {
        width: 100%;
        max-width: none;
        min-width: 0;
    }

    .fi-kanban-search-inner {
        position: relative;
        width: 100%;
    }

    .fi-kanban-search-icon {
        position: absolute;
        top: 50%;
        left: 0.75rem;
        z-index: 2;
        display: flex;
        align-items: center;
        transform: translateY(-50%);
        color: #9ca3af;
        pointer-events: none;
    }

    .fi-kanban-search-icon svg {
        width: 1.25rem;
        height: 1.25rem;
    }

    .fi-kanban-search-input {
        display: block;
        width: 100%;
        height: 2.5rem;
        min-height: 2.5rem;
        padding: 0 0.875rem 0 2.75rem;
        border: 0;
        border-radius: 0.5rem;
        background: #f9fafb;
        color: #111827;
        box-shadow: none;
    }

    .fi-kanban-search-input:focus {
        background: #ffffff;
        outline: 2px solid #f59e0b;
        outline-offset: 0;
    }

    .fi-kanban-filter {
        position: relative;
    }

    .fi-kanban-filter-button {
        display: inline-flex !important;
        width: 2.5rem;
        height: 2.5rem;
        align-items: center;
        justify-content: center;
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
        background: #f9fafb;
        color: #4b5563;
        box-shadow: none;
    }

    .fi-kanban-filter-button:hover {
        border-color: #f59e0b;
        background: #fffbeb;
        color: #b45309;
    }

    .fi-kanban-filter-button svg {
        width: 1.25rem;
        height: 1.25rem;
    }

    .dark .fi-kanban-toolbar {
        border-color: rgb(255 255 255 / 0.10);
        background: #111827;
    }

    .dark .fi-kanban-search-input,
    .dark .fi-kanban-filter-button {
        border-color: rgb(255 255 255 / 0.10);
        background: rgb(255 255 255 / 0.05);
        color: #e5e7eb;
    }

    .dark .fi-kanban-search-input:focus {
        background: rgb(255 255 255 / 0.08);
    }
</style>

<style>
    /* Posiciona o filtro dentro da área visível do painel. */
    .fi-kanban-filter-popover {
        right: 0;
        left: auto;
        z-index: 50;
        width: 20rem;
        max-width: calc(100vw - 2rem);
    }

    @media (max-width: 640px) {
        .fi-kanban-filter-popover {
            right: 0;
            left: auto;
            width: min(20rem, calc(100vw - 2rem));
        }
    }
</style>

<style>
    /* Ações do filtro operacional do Kanban */
    .fi-kanban-filter-heading {
        margin-bottom: 0.75rem;
    }

    .fi-kanban-filter-heading h4 {
        margin: 0;
        color: #111827;
        font-size: 0.875rem;
        font-weight: 600;
    }

    .fi-kanban-filter-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 0.5rem;
        margin-top: 1rem;
        padding-top: 0.75rem;
        border-top: 1px solid #e5e7eb;
    }

    .fi-kanban-filter-clear,
    .fi-kanban-filter-apply {
        min-height: 2.25rem;
        border-radius: 0.5rem;
        padding: 0.5rem 0.875rem;
        font-size: 0.875rem;
        font-weight: 600;
        cursor: pointer;
    }

    .fi-kanban-filter-clear {
        border: 1px solid #d1d5db;
        background: #ffffff;
        color: #374151;
    }

    .fi-kanban-filter-clear:hover {
        background: #f9fafb;
    }

    .fi-kanban-filter-apply {
        border: 1px solid #d97706;
        background: #d97706;
        color: #ffffff;
    }

    .fi-kanban-filter-apply:hover {
        background: #b45309;
    }

    .dark .fi-kanban-filter-heading h4 {
        color: #ffffff;
    }

    .dark .fi-kanban-filter-actions {
        border-top-color: rgb(255 255 255 / 0.10);
    }

    .dark .fi-kanban-filter-clear {
        border-color: rgb(255 255 255 / 0.15);
        background: rgb(255 255 255 / 0.05);
        color: #e5e7eb;
    }
</style>
