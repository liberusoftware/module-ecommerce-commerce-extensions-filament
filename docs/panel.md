# The panel

What this package puts on a merchant's screen, why each screen is shaped the way
it is, and what the domain package does not publish that a panel wanted.

## The screen that matters

**A signing secret is returned exactly once, and this is the only surface that
can show it.** `AddEndpoint` and `RotateSecret` return an `IssuedSecret`; both
columns behind it are `encrypted` and `$hidden`, and no query, event or
serialisation gives one back. A control that added an endpoint and redirected to
a list would have destroyed the credential it just minted, and the only recovery
would be another rotation.

So both actions raise a **persistent** notification carrying the secret itself,
in the same response that mints it. Persistent, not a toast on a timer: the
notification *is* the secret's existence outside the module. `-livewire` cannot
do this — a value it held would be destroyed by a redirect or published in the
page payload on every subsequent request — and `-api` returns it in a response
body, which is not a screen. If this package declined too, an operator using the
panel would have no way to see a secret at all.

A modal that had to be dismissed would be stronger than a notification. It needs
`replaceMountedAction` and a second action whose modal exists only to display an
argument, and the notification is persistent and tested. **ponytail:** upgrade it
if an operator ever reports losing one.

`AddEndpoint` refuses a duplicate rather than answering with the endpoint that
exists, for the same reason — serving the retry would mean issuing a second
secret — and the panel says so rather than reporting a failure.

## The rules this panel keeps

- **No `visible()` guard on any action, anywhere.** Filament re-evaluates one
  against a freshly hydrated record at mount *and* at `callMountedAction`, so a
  guard restating "this delivery has already settled" or "there is no previous
  secret to expire" would make the domain's own refusal unreachable through the
  panel. The guards are dropped, the branches stay, and both refusals are
  exercised by the suite.
- **A refusal is not a failure.** `NoTransportBound`, `EndpointRetired`,
  `ExtensionRetired`, `DestinationIsNotHttps`, `DestinationIsNotPublic`,
  `WindowClosed` and `ConcurrentAttempt` each have a sentence in
  `Support\Render`, and every match over them has no default arm, so a case the
  domain adds fails `RenderTest` rather than rendering a blank.
- **Two nulls that are facts, not absences.** A delivery with no outcome is
  *still owed* — rendered as those words, never as an em dash. An attempt with a
  response excerpt and no status is a **transport that never completed**, so its
  excerpt is an exception message rather than a response body, and the column is
  not labelled "response".
- **One clock, one place.** The domain reads no clock; every action takes a
  `CarbonImmutable`. `Support\Snapshot::asOf()` is the only `now()` in the
  package, asserted by `ModuleBoundaryRulesTest`.
- **Nothing is written through Eloquent.** Every write is a published action
  taking the merchant as its first argument.
- **Two wrong answers are one answer.** Another merchant's record and one that
  does not exist both raise `ModelNotFoundException`, and nothing checks custody
  before the lookup.

## What is deliberately not here

- **No control that raises an event.** Raising one means handing in a name, a
  cause, a subject and already-serialised bytes. A panel has none of those, and a
  button that made a payload up would be this package building one — the single
  thing the module refuses to do. Whoever owns the event calls `RaiseEvent`. It
  follows that `CauseReferenceIsClaimed` and `RaisedDelivery::$alreadyRaised`
  have no rendering here; they belong to `-api`.
- **No prune control.** Retention is the host's policy and ships as a scheduled
  command. A button that deleted a security log on somebody's judgement is not a
  button.
- **No export and no erasure.** Both span merchants and say so in their names. A
  screen scoped to one merchant must not carry a control that acts on all of
  them.
- **No manifest, install, update, uninstall, scope grant, rollback, health model
  or UI-extension registry.** The domain ships none of them, and a surface for
  something that does not exist is a lie with a navigation entry. An extension
  here is a record; `liberusoftware/module-manager` remains the only registrar.

## Gaps found in `liberusoftware/ecommerce-commerce-extensions` 0.1.0

Reported, not closed here. Each is a domain decision.

1. **`Seams::transport()` raises instead of refusing.** It resolves a configured
   class name through `App::make()` outside any `try`, and `AttemptDelivery` does
   not catch it — so a host config typo is a `BindingResolutionException` with no
   attempt row, no refusal row and the delivery untouched. It is the one way an
   attempt can fail that is not a row, which breaks the module's own rule.
   `Pages\ViewDelivery` catches it and says which of the three states the seam is
   in; `Support\Snapshot::transportBound()` does the same for the widget.
2. **`Seams::transport()` also answers `null` for a binding that resolves to
   something which is not a `DeliveryTransport`.** That is a misconfiguration
   reported as an unbound seam, and the two need different instructions.
3. **`AttemptDelivery` stores `duration_ms = 0` when the transport throws** — a
   zero substituted for an elapsed time nobody measured, on a nullable column.
   `Render::took()` suppresses it alongside the null status.
4. **The domain publishes no query over attempts.** `ListDeliveries` answers for
   deliveries and nothing answers for attempts, so the two figures the standing
   widget needs are counted by reaching for the `Attempt` model directly. That is
   the only place this package touches a model query, and the boundary suite pins
   it to one file.
5. **The domain publishes no aggregate of any kind.** Every count on the standing
   widget is assembled here from published queries — four for the delivery states
   and seven for the refusal reasons. They are counts over indexed, tenant-scoped
   queries rather than a read of every row into memory, which is the host fault
   the module was extracted over; a `countsByOutcome` query in the domain would
   make eleven round trips into two.
6. **`ListDueDeliveries` carries a `limit` that `count()` ignores.** A paginated
   table supersedes the limit and a count does not, so pairing the two on one
   screen would let a widget disagree with itself. Nothing here renders a count of
   what is due, and `DueNow::dueIds()` documents that it can only be a prefix.
7. **`CustodyPolicy` has no `ownsEventName` and no `ownsSubscription`.** It
   answers for an extension, an endpoint, a delivery and an attempt. The event
   name registry has no view page here so nothing needed it, but the omission is
   asymmetric.
8. **`Unsubscribe` returns a bare `bool`** where every other verb returns a model
   or a DTO. Nothing distinguishes "there was nothing to unsubscribe" from a
   refusal, so this panel reports one sentence that is true either way.
