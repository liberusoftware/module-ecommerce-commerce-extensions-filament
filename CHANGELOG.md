# Changelog

## 0.1.0

The Filament surface of `liberusoftware/ecommerce-commerce-extensions`.

### Shipped

- Four resources — extensions, endpoints, event names and deliveries — each over
  the domain's own published query, with the merchant derived once in
  `Support\PanelTenant`.
- Two relation managers: an endpoint's subscriptions, and a delivery's attempts.
- Two widgets: the standing of the transport seam and of every delivery, and the
  deliveries owed an attempt right now. Both override `canView()` and state the
  merchant in their own query, because a widget is the surface Filament's own
  tenancy does not reach.
- Every published domain verb a merchant can reasonably operate: register and
  retire and reinstate an extension; add, rotate, expire, retire, reinstate,
  subscribe and unsubscribe an endpoint; register an event name; attempt one
  delivery.
- **The signing secret, shown once, in the same response that mints it.** This is
  the only one of the three surfaces that can display it: the domain returns it
  exactly once and nothing reads one back.

### Decisions

- **No `visible()` guard on any action.** Filament re-evaluates one at mount and
  again at `callMountedAction`, so a guard restating a domain rule makes the
  domain's own refusal unreachable. Both refusals — a settled delivery and an
  endpoint with no previous secret — are reached through the panel and tested.
- **A refusal is rendered as its reason**, never as a failure and never as zero
  deliveries. A delivery with no outcome is *still owed*, not an em dash. An
  attempt with an excerpt and no status is a transport that never completed, so
  its excerpt is an exception message and its stored zero duration is suppressed.
- **The transport seam has three states**, not two: bound, nothing bound, and
  configured with something the container cannot build.
- One clock per request, in `Support\Snapshot`, pinned by a boundary test.

### Deliberately not shipped

- **No control that raises an event.** Raising one means handing in
  already-serialised bytes, which a panel does not have; a button that invented a
  payload would be this package building one.
- **No prune, no export, no erasure.** Retention is a scheduled command, and both
  of the others span every merchant.
- **No manifest, install, update, uninstall, scope grant, rollback, health model
  or UI-extension registry.** The domain ships none of them. An extension is a
  record; `liberusoftware/module-manager` remains the only registrar.
- No create, edit or delete control of any kind. Every write is a published
  domain action; nothing in this domain is deleted.

Gaps found in the domain package are recorded in `docs/panel.md`.
