# ADR 0004 — Filament como interface administrativa

## Status

Aprovada

## Contexto

O VANGUARD utilizará Filament para acelerar a construção de painéis administrativos, formulários, tabelas e páginas internas.

Entretanto, o VANGUARD não deve ser arquitetado a partir do Filament. O domínio da plataforma deve permanecer independente da interface.

## Decisão

O Filament será utilizado exclusivamente como camada de UI administrativa.

Resources, Pages, Widgets e Actions do Filament não deverão conter regras de negócio centrais.

A regra de negócio deverá ficar em casos de uso, serviços de aplicação, domínio, engines ou módulos apropriados.

## Consequências

- A interface administrativa pode evoluir sem comprometer o domínio.
- O sistema poderá ter outras interfaces no futuro, como API, aplicativo mobile ou integrações externas.
- Resources do Filament deverão chamar casos de uso, não implementar diretamente decisões operacionais.
- O domínio continuará soberano sobre tecnologia, tela e framework.
