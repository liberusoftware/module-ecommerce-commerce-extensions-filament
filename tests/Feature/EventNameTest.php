<?php

declare(strict_types=1);

use Liberu\Ecommerce\CommerceExtensions\Filament\Resources\EventNames\Pages\ListEventNames;
use Liberu\Ecommerce\CommerceExtensions\Filament\Tests\TestTenant;
use Liberu\Ecommerce\CommerceExtensions\Models\EventName;
use Livewire\Livewire;

it('registers a name an endpoint may subscribe to', function (): void {
    Livewire::test(ListEventNames::class)
        ->callAction('register', ['name' => 'order.paid', 'description' => 'An order was paid for']);

    expect(lastNotification()['title'])->toBe('Registered')
        ->and(EventName::query()->where('tenant_id', TestTenant::PRIMARY)->count())->toBe(1);
});

it('refuses a duplicate name rather than registering it twice', function (): void {
    anEventName();

    Livewire::test(ListEventNames::class)
        ->callAction('register', ['name' => 'order.paid', 'description' => null]);

    $notification = lastNotification();

    expect($notification['title'])->toBe('Nothing was registered')
        ->and($notification['color'])->toBe('danger')
        ->and(EventName::query()->where('tenant_id', TestTenant::PRIMARY)->count())->toBe(1);
});

it('lists only this merchant’s registry, where both merchants registered the same name', function (): void {
    // The registry is per merchant. `order.paid` meaning something to one of
    // them says nothing about the other.
    $mine = anEventName();
    anEventName(TestTenant::OTHER);

    Livewire::test(ListEventNames::class)
        ->assertCanSeeTableRecords(EventName::query()->whereKey($mine->getKey())->get())
        ->assertCanNotSeeTableRecords(EventName::query()->where('tenant_id', TestTenant::OTHER)->get());
});
