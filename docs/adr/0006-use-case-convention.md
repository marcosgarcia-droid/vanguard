# ADR 0006 — Convenção para casos de uso

## Status

Aprovada

## Contexto

O VANGUARD será orientado por domínio e casos de uso.

A camada de interface, incluindo Filament, API REST, mobile ou qualquer outra UI futura, não deverá conter regra de negócio central.

Inicialmente foi criado um contrato UseCase com um método genérico:

public function execute(Command|Query $input): mixed;

Essa abordagem padroniza a chamada, mas dificulta casos de uso fortemente tipados, pois implementações concretas não devem ser obrigadas a aceitar qualquer Command ou Query.

Por exemplo, um caso de uso como CreateOrganizationUseCase deveria poder receber diretamente um CreateOrganizationCommand, sem perder clareza ou tipagem.

## Decisão

UseCase será uma interface marcadora.

Cada caso de uso deverá declarar explicitamente seu método público principal, preferencialmente execute, recebendo o comando ou consulta específica daquele caso de uso.

Exemplo conceitual:

final class CreateOrganizationUseCase implements UseCase
{
    public function execute(CreateOrganizationCommand $command): Organization
    {
        // Regras de aplicação aqui.
    }
}

Comandos e consultas continuarão existindo como contratos separados:

interface Command
{
}

interface Query
{
}

A diferença é que o contrato UseCase não irá impor uma assinatura genérica obrigatória.

## Consequências

- Casos de uso permanecem fortemente tipados.
- A camada Application fica mais clara.
- Filament, API ou qualquer outra UI deverá chamar casos de uso concretos.
- O contrato UseCase identifica intenção arquitetural sem engessar assinatura.
- Um dispatcher genérico de casos de uso só será criado se houver necessidade real.
- O domínio permanece independente da interface.
- A arquitetura evita acoplamento prematuro com padrões excessivamente genéricos.
