<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CommerceExtensions\Filament;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Liberu\Ecommerce\CommerceExtensions\Filament\Resources\Deliveries\DeliveryResource;
use Liberu\Ecommerce\CommerceExtensions\Filament\Resources\Endpoints\EndpointResource;
use Liberu\Ecommerce\CommerceExtensions\Filament\Resources\EventNames\EventNameResource;
use Liberu\Ecommerce\CommerceExtensions\Filament\Resources\Extensions\ExtensionResource;
use Liberu\Ecommerce\CommerceExtensions\Filament\Support\PanelTenant;
use Liberu\Ecommerce\CommerceExtensions\Filament\Widgets\DeliveryStanding;
use Liberu\Ecommerce\CommerceExtensions\Filament\Widgets\DueNow;

/**
 * What a merchant has told to listen, as the host attaches it.
 *
 *     $panel->plugin(
 *         CommerceExtensionsPlugin::make()
 *             ->tenantUsing(fn (): string => (string) Filament::getTenant()?->getKey()),
 *     );
 *
 * Both widgets are registered here rather than discovered, so they arrive on the
 * panel the host chose rather than on whichever one happened to boot.
 */
final class CommerceExtensionsPlugin implements Plugin
{
    public static function make(): self
    {
        return new self();
    }

    public function getId(): string
    {
        return 'ecommerce-commerce-extensions';
    }

    /** How this panel names the merchant. Without it, the panel's own Filament tenant is used. */
    public function tenantUsing(?Closure $resolver): self
    {
        PanelTenant::resolveUsing($resolver);

        return $this;
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                ExtensionResource::class,
                EndpointResource::class,
                EventNameResource::class,
                DeliveryResource::class,
            ])
            ->widgets([
                DeliveryStanding::class,
                DueNow::class,
            ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
