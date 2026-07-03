# ADR 0003 — Redis para cache, filas e sessões

## Status

Aprovada

## Contexto

O VANGUARD terá eventos, notificações, integrações, jobs assíncronos, workers e processos operacionais que exigem infraestrutura eficiente para cache e filas.

## Decisão

O Laravel utilizará Redis para:

- cache;
- filas;
- sessões.

## Consequências

- O sistema fica preparado para jobs assíncronos desde a Foundation.
- Integrações futuras poderão usar filas sem refatoração estrutural.
- Sessões e cache deixam de depender do banco relacional.
- O container PHP deverá possuir a extensão `redis` habilitada.
