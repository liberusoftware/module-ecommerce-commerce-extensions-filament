<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CommerceExtensions\Filament\Resources\Deliveries\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Liberu\Ecommerce\CommerceExtensions\Filament\Resources\Deliveries\DeliveryResource;

/**
 * There is no control here that raises an event.
 *
 * Raising one means handing in a name, a cause, a subject and already-serialised
 * bytes, and a panel has none of those to hand in: a button that made a payload
 * up would be this package building one, which is the single thing the module
 * refuses to do. Whoever owns the event calls `RaiseEvent`.
 */
final class ListDeliveries extends ListRecords
{
    protected static string $resource = DeliveryResource::class;

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
