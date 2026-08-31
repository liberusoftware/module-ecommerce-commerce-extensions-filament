# Adoption

## Install

```bash
composer require liberusoftware/ecommerce-commerce-extensions-filament
```

The domain package is not on Packagist yet, so `composer.json` carries a `vcs`
repository entry for it. Its presence is the information: remove it when the
domain package is published.

Installing boots nothing. `extra.laravel.providers` is absent on purpose and the
host's module manager registers the provider only when the module is named in
`MODULES_ENABLED`.

## Attach it to a panel

```php
use Liberu\Ecommerce\CommerceExtensions\Filament\CommerceExtensionsPlugin;

public function panel(Panel $panel): Panel
{
    return $panel->plugin(
        CommerceExtensionsPlugin::make()
            ->tenantUsing(fn (): string => (string) Filament::getTenant()?->getKey()),
    );
}
```

Every resource and both widgets arrive through the plugin. The service provider
registers nothing, so nothing can appear on a panel that did not ask for it.

**`tenantUsing()` is the whole of the wiring.** Filament's tenancy resolves the
host's `Team`; the merchant this module scopes on is derived from it once, in
`Support\PanelTenant`. Without a resolver the panel's own Filament tenant is used,
and a panel with neither raises rather than falling back — `where('tenant_id',
null)` compiles to `is null`, which lists exactly the orphan rows a scope exists
to hide.

The four resources set `$isScopedToTenant = false` deliberately: Filament's
tenancy would scope on the panel's tenant model, and these tables carry an opaque
`tenant_id` string this module never resolves.

## What the host must bind

Nothing, for the panel to render. Every screen works with no transport bound and
says so — that is what the standing widget is for.

To make a delivery actually go, bind one implementation of
`Liberu\Ecommerce\CommerceExtensions\Contracts\DeliveryTransport`:

```php
// config/commerce_extensions.php
'transport' => App\Webhooks\CurlTransport::class,
```

A class name the container cannot build is a **third** state, not an unbound one.
The panel says so rather than showing an error page, but the domain raises rather
than refusing, so nothing is recorded when it happens. Get the class name right.

## What the host must schedule

The domain package ships two commands and schedules neither, because a package
that scheduled work would run it on every host that installed it. The panel's
"Attempt it now" button is for one delivery; the sweep is the host's:

```php
// routes/console.php
Schedule::command('commerce-extensions:deliver-due', ['<tenant>'])->everyFiveMinutes();
Schedule::command('commerce-extensions:prune-deliveries', ['<tenant>', '--days=90'])->daily();
```

Both take one merchant. A host with many merchants loops.

## What it deletes from the host

Nothing yet. The host's `WebhookEndpointController`, `DispatchOutboundWebhook`,
`SendWebhookDelivery`, `RetryFailedWebhooks` and the two `webhook_*` tables are
retired by adopting the **domain** package; this one only presents it. See that
package's `docs/adoption.md`.
