# ADR 0007: CNPJ lookup provider failover

## Status

Accepted

## Context

The VANGUARD platform needs to retrieve Brazilian company registration data by CNPJ.

Relying on a single external API creates operational risk. If the primary provider is slow, unavailable, rate-limited, or returns an invalid response, the user experience can be interrupted.

The system currently supports CNPJ lookup through multiple providers:

- BrasilAPI
- ReceitaWS

The provider order is configurable through environment variables.

## Decision

VANGUARD will not bind the CNPJ lookup use case directly to a single external provider.

Instead, the application binds the `CnpjLookupProvider` contract to a failover provider:

```text
CnpjLookupProvider
↓
FailoverCnpjLookupProvider
↓
BrasilApiCnpjLookupProvider
↓
ReceitaWsCnpjLookupProvider
````

The provider order is controlled by:

```text
VANGUARD_CNPJ_LOOKUP_PROVIDERS=brasilapi,receitaws
```

Each provider attempt must be recorded in `organization_cnpj_syncs`.

This means a lookup can produce more than one sync record:

```text
BrasilAPI failed  -> failed sync
ReceitaWS success -> success sync
```

If all configured providers fail, the system records all failed attempts and raises a provider exception.

## Consequences

### Positive

* The user experience is protected from a single provider outage.
* The system can add more providers in the future without changing the use case.
* The audit trail records every external attempt.
* Provider order can be changed per environment without code changes.
* Failures are observable and traceable.

### Negative

* A single lookup can create multiple sync records.
* Error handling is more complex than a single-provider integration.
* Different providers return different payload formats, requiring normalization.

## Implementation Notes

The main implementation pieces are:

* `CnpjLookupProvider`
* `CnpjLookupAttempt`
* `CnpjLookupAttemptAwareProvider`
* `CnpjLookupProviderException`
* `BrasilApiCnpjLookupProvider`
* `ReceitaWsCnpjLookupProvider`
* `FailoverCnpjLookupProvider`
* `LookupOrganizationByCnpjUseCase`
* `organization_cnpj_syncs`

The current default order is:

```text
brasilapi,receitaws
```

This decision may be revisited if a paid/private provider becomes the primary source of truth.
