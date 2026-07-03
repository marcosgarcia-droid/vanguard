# ADR 0001 — Docker Compose próprio como fundação do projeto

## Status

Aprovada

## Contexto

O VANGUARD será desenvolvido localmente em WSL2 com Docker e posteriormente implantado em servidor. O projeto precisa manter consistência entre ambiente local, homologação e produção.

## Decisão

O projeto utilizará Docker Compose próprio, versionado no repositório, em vez de depender do Laravel Sail como base principal.

A aplicação Laravel ficará dentro de `src/`.

A infraestrutura Docker ficará fora da aplicação, na raiz do projeto.

## Consequências

- O ambiente local fica mais próximo do futuro ambiente de deploy.
- A infraestrutura passa a ser parte explícita da arquitetura do projeto.
- PHP, Composer, Node, Nginx, MySQL, Redis e Mailpit rodam em containers.
- O WSL2 não precisa ter PHP, Composer ou Node instalados diretamente.
- O projeto ganha mais controle sobre extensões PHP e serviços auxiliares.
