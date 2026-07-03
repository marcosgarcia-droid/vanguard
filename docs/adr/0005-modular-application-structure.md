# ADR 0005 — Estrutura modular da aplicação

## Status

Aprovada

## Contexto

O VANGUARD será uma plataforma de coordenação operacional de longo prazo. A aplicação não deve ser organizada apenas por tecnologia, controllers ou telas.

## Decisão

A aplicação será organizada em quatro grandes áreas dentro de `src/app`:

- `Core`: engines e capacidades compartilhadas;
- `Modules`: domínios funcionais da plataforma;
- `Infrastructure`: integrações, persistência, hardware e serviços externos;
- `Support`: contratos, DTOs, enums, exceptions, helpers e value objects compartilhados.

Cada módulo seguirá a estrutura padrão:

- `Domain`;
- `Application`;
- `Infrastructure`;
- `UI`.

## Consequências

- O domínio permanece separado da interface.
- O Filament fica restrito à camada de UI.
- Novos módulos terão uma convenção clara desde o início.
- Engines compartilhadas não serão duplicadas dentro dos módulos.
- A arquitetura fica preparada para crescimento gradual sem reescrita estrutural.
