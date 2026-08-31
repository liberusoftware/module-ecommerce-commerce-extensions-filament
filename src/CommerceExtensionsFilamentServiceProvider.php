<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CommerceExtensions\Filament;

use Illuminate\Support\ServiceProvider;

/**
 * Registers nothing. Every screen and both widgets arrive through
 * {@see CommerceExtensionsPlugin}, so the host decides which panel gets them —
 * a provider that registered widgets would put one merchant's endpoints on
 * whatever panel happened to boot.
 */
class CommerceExtensionsFilamentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        //
    }
}
