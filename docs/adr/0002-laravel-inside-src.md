# ADR 0002 — Laravel isolado na pasta src

## Status

Aprovada

## Contexto

O VANGUARD precisa separar aplicação e infraestrutura desde o início.

## Decisão

A aplicação Laravel ficará dentro da pasta `src/`.

A raiz do repositório conterá a infraestrutura do projeto, incluindo Docker Compose, Dockerfiles, documentação e arquivos de governança técnica.

## Consequências

- A estrutura do repositório fica mais clara.
- O Laravel não se mistura com arquivos de infraestrutura.
- O deploy futuro pode tratar aplicação e infraestrutura separadamente.
- A documentação arquitetural fica no mesmo repositório, mas fora da aplicação Laravel.
