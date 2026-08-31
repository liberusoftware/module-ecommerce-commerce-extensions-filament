# Ecommerce: Commerce Extensions Filament

> This optional Filament 5 presentation package presents exactly one independent domain module. It contributes reusable resources, pages, widgets, schemas, tables, infolists, and actions to application-owned panels while delegating authorization, validation, tenancy, persistence, and business rules to the ecommerce-commerce-extensions public bounda

[Software](https://liberusoftware.com) ·
[Hosting](https://liberuhosting.com) ·
[Services](https://liberuservices.com) ·
[Liberu Group](https://liberugroup.com)

![PHP](https://img.shields.io/badge/PHP-8.5-777BB4?logo=php&logoColor=white) ![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white) ![Filament](https://img.shields.io/badge/Filament-5-FDAE4B)
[![Latest release](https://img.shields.io/github/v/release/liberusoftware/module-ecommerce-commerce-extensions-filament?sort=semver)](https://github.com/liberusoftware/module-ecommerce-commerce-extensions-filament/releases/latest) [![Tests](https://github.com/liberusoftware/module-ecommerce-commerce-extensions-filament/actions/workflows/tests.yml/badge.svg?branch=main)](https://github.com/liberusoftware/module-ecommerce-commerce-extensions-filament/actions/workflows/tests.yml)

## What it presents

The operator's view of `liberusoftware/ecommerce-commerce-extensions`: the third
parties a merchant has registered to receive events, the endpoints they receive
at, what each one is subscribed to, and every attempt ever made to deliver to
them.

**The one fact that shaped it.** A signing secret is returned exactly once, at
issue, and nothing reads one back — both columns are encrypted and hidden, and no
query, event or serialisation gives one up. So a control that added an endpoint
and redirected to a list would have destroyed the credential it just minted. Both
actions that issue one put the secret itself in a persistent notification in the
same response, and this is the only one of the three surfaces that can: a
Livewire component cannot hold it without republishing it, and an API response is
not a screen.

**The second fact.** Nothing is bound to carry a signed request until a host binds
it, because a package cannot know a host's egress arrangements. A merchant looking
at this panel on a fresh install must see *why* nothing has gone —
`NoTransportBound` is the truthful answer and it is not an error. Every screen
here renders the refusal; none of them renders an empty table that reads as
"nothing to do".

| Screen | What it answers |
|---|---|
| **Extensions** | Which third parties this merchant has registered, how many endpoints each holds, and which have been retired — a record, never a PHP package, and registering one boots no code |
| **Endpoints** | Where each extension receives, whether a rotation overlap is running, and how much it is subscribed to. Adding one issues a secret and shows it once |
| **Event names** | What may be subscribed to at all. A name registered here or a subscription refused — never a string accepted and then silently matched against nothing |
| **Deliveries** | One event owed to one endpoint, the bytes stored for it, and every attempt with what came back. The log outlives the endpoint |
| **Delivery standing** (widget) | Whether anything can carry a request, what is still owed, and every refusal by reason — so the cause is on the first screen rather than inferred from an absence |
| **Owed an attempt right now** (widget) | What the domain says is due at this instant, measured against one clock the page read once |

## What it does not own

No business rule, no arithmetic, no transport and no decision. Every write is a
published domain action taking the merchant as its first argument. There is no
create, edit or delete control anywhere; no control that raises an event, because
a panel has no payload to hand in; and no control that spans more than one
merchant.

Nothing here installs, updates or uninstalls anything. `liberusoftware/module-manager`
decides which code boots, resolved once from configuration.

`docs/panel.md` carries the decisions and the gaps found in the domain package.

## Requirements

- **PHP 8.5**
- **Composer 2**
- A supported database (e.g. MySQL, PostgreSQL, SQLite)

## Quick start

To install this package via Composer, run:

```bash
composer require liberusoftware/module-ecommerce-commerce-extensions-filament
```

## Documentation

- [Liberu Main Documentation](https://github.com/liberusoftware/documentation)
- [Architecture & Standards Index](https://github.com/liberusoftware/documentation/tree/main/architecture)

## Related Liberu Projects

| Project | Repository | Purpose |
| --- | --- | --- |
| **Boilerplate** | [liberusoftware/boilerplate-laravel](https://github.com/liberusoftware/boilerplate-laravel) | Shared Laravel application foundation and reference composition |
| **CMS** | [liberu-cms/cms-laravel](https://github.com/liberu-cms/cms-laravel) | Structured content, publishing, media, multisite, and headless delivery |
| **CRM** | [liberu-crm/crm-laravel](https://github.com/liberu-crm/crm-laravel) | Customer data, sales, marketing, service, and customer success |
| **Billing** | [liberu-billing/billing-laravel](https://github.com/liberu-billing/billing-laravel) | Products, subscriptions, invoicing, payments, and provisioning |
| **Accounting** | [liberu-accounting/accounting-laravel](https://github.com/liberu-accounting/accounting-laravel) | Ledgers, banking, tax, expenses, close, and financial reporting |
| **Ecommerce** | [liberu-ecommerce/ecommerce-laravel](https://github.com/liberu-ecommerce/ecommerce-laravel) | Catalog, checkout, orders, fulfillment, returns, B2B, and omnichannel commerce |
| **Control Panel** | [liberu-control-panel/control-panel-laravel](https://github.com/liberu-control-panel/control-panel-laravel) | Hosting, infrastructure, DNS, mail, databases, backups, and security operations |
| **Automation** | [liberu-automation/automation-laravel](https://github.com/liberu-automation/automation-laravel) | Governed workflows, provider-neutral AI, approvals, and connectors |

## Security

Please do not report security vulnerabilities through public GitHub issues.
Follow our [Security Policy](https://github.com/liberusoftware/documentation/blob/main/architecture/SECURITY.md) for private reporting and supported versions.

## License

This project is open-source software. You may use, modify, and distribute it
under the terms described in [LICENSE.md](LICENSE.md).

The linked license text is authoritative; this summary is not legal advice.

## Feedback and contributing

Feedback and contributions are welcome. You can help by reporting reproducible
bugs, proposing focused enhancements, improving documentation or translations,
and submitting tested code changes.

Before contributing, please read [CONTRIBUTING.md](https://github.com/liberusoftware/documentation/blob/main/standards/CONTRIBUTING.md) and our
[Code of Conduct](https://github.com/liberusoftware/documentation/blob/main/architecture/CODE_OF_CONDUCT.md). Search existing issues first, then use
the appropriate issue template. Pull requests should explain the problem and
approach, remain focused, include or update tests, pass the required workflows,
and document user-visible or breaking changes.

## Contributors

Thank you to everyone who helps improve Liberu.

<a href="https://github.com/liberusoftware/module-ecommerce-commerce-extensions-filament/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=liberusoftware/module-ecommerce-commerce-extensions-filament" alt="Contributors to liberusoftware/module-ecommerce-commerce-extensions-filament">
</a>

[View the full contributors graph](https://github.com/liberusoftware/module-ecommerce-commerce-extensions-filament/graphs/contributors).
