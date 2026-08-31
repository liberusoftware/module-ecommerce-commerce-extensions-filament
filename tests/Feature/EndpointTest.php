<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Liberu\Ecommerce\CommerceExtensions\Enums\RefusalReason;
use Liberu\Ecommerce\CommerceExtensions\Filament\Resources\Endpoints\EndpointResource;
use Liberu\Ecommerce\CommerceExtensions\Filament\Resources\Endpoints\Pages\ListEndpoints;
use Liberu\Ecommerce\CommerceExtensions\Filament\Resources\Endpoints\Pages\ViewEndpoint;
use Liberu\Ecommerce\CommerceExtensions\Filament\Resources\Endpoints\RelationManagers\SubscriptionsRelationManager;
use Liberu\Ecommerce\CommerceExtensions\Filament\Resources\Extensions\Pages\ViewExtension;
use Liberu\Ecommerce\CommerceExtensions\Filament\Support\Render;
use Liberu\Ecommerce\CommerceExtensions\Filament\Support\Snapshot;
use Liberu\Ecommerce\CommerceExtensions\Filament\Tests\TestTenant;
use Liberu\Ecommerce\CommerceExtensions\Models\Endpoint;
use Livewire\Livewire;

it('shows the signing secret at the one moment it exists outside the module', function (): void {
    // The thing this surface is most likely to get wrong. `AddEndpoint` returns
    // the secret once, both columns are encrypted and hidden, and no query,
    // event or serialisation gives one back — so a control that added an
    // endpoint and redirected to a list would have destroyed the credential it
    // just minted, and the only recovery would be a rotation.
    $extension = anExtension();

    Livewire::test(ListEndpoints::class)
        ->callAction('add', ['extension' => (string) $extension->id, 'url' => RECEIVES_AT]);

    $notification = lastNotification();
    $endpoint = Endpoint::query()->firstOrFail();

    expect($notification['title'])->toBe('Added. Here is the secret, once')
        ->and($notification['body'])->toContain('whsec_')
        ->and($notification['body'])->toContain($endpoint->secret)
        ->and($notification['color'])->toBe('success');
});

it('refuses a URL that resolves into private space, with the reason the delivery side records', function (): void {
    $extension = anExtension();

    Livewire::test(ListEndpoints::class)
        ->callAction('add', ['extension' => (string) $extension->id, 'url' => 'https://127.0.0.1/hook']);

    $notification = lastNotification();

    expect($notification['title'])->toBe('No endpoint was added, and no secret was issued')
        ->and($notification['body'])->toContain(Render::refusal(RefusalReason::DestinationIsNotPublic))
        ->and(Endpoint::query()->count())->toBe(0);
});

it('refuses a URL that is not https at all', function (): void {
    $extension = anExtension();

    Livewire::test(ListEndpoints::class)
        ->callAction('add', ['extension' => (string) $extension->id, 'url' => 'http://receiver.invalid/hook']);

    expect(lastNotification()['body'])->toContain(Render::refusal(RefusalReason::DestinationIsNotHttps));
});

it('refuses a duplicate rather than handing the same endpoint a second secret', function (): void {
    $extension = anExtension();
    anEndpoint();

    Livewire::test(ListEndpoints::class)
        ->callAction('add', ['extension' => (string) $extension->id, 'url' => RECEIVES_AT]);

    $notification = lastNotification();

    expect($notification['title'])->toBe('No endpoint was added, and no secret was issued')
        ->and($notification['body'])->toContain('already has an endpoint')
        ->and($notification['body'])->toContain('Rotate the existing endpoint')
        ->and(Endpoint::query()->count())->toBe(1);
});

it('names every extension in the select, retired ones included', function (): void {
    $extension = anExtension();

    expect(ListEndpoints::extensionOptions())->toBe([$extension->id => 'A Partner — Live']);

    Livewire::test(ViewExtension::class, ['record' => $extension->getKey()])->callAction('retire');

    expect(ListEndpoints::extensionOptions())->toBe([$extension->id => 'A Partner — Retired']);
});

it('rotates, shows the new secret once, and says the old one is still live', function (): void {
    $endpoint = anEndpoint();

    Livewire::test(ViewEndpoint::class, ['record' => $endpoint->getKey()])->callAction('rotate');

    $notification = lastNotification();
    $rotated = $endpoint->fresh();

    expect($notification['title'])->toBe('Rotated. Here is the secret, once')
        ->and($notification['body'])->toContain((string) $rotated?->secret)
        ->and($notification['body'])->toContain('previous secret stays live')
        ->and($rotated?->previous_secret_expires_at)->not->toBeNull()
        ->and(EndpointResource::rotation($rotated ?? $endpoint))->toContain('keeps verifying');
});

it('says one secret is live before a rotation, and says the overlap has closed after it lapses', function (): void {
    $endpoint = anEndpoint();

    expect(EndpointResource::rotation($endpoint))->toContain('One secret is live');

    Livewire::test(ViewEndpoint::class, ['record' => $endpoint->getKey()])->callAction('rotate');

    Carbon::setTestNow(Carbon::parse('2026-09-02 12:00:00'));
    Snapshot::forget();

    expect(EndpointResource::rotation($endpoint->fresh() ?? $endpoint))->toContain('overlap closed');
});

it('closes the overlap early, and refuses when there is none to close', function (): void {
    // No `visible()` guard, so the domain's own refusal is reachable through
    // the panel rather than hidden behind a button that is not drawn.
    $endpoint = anEndpoint();

    Livewire::test(ViewEndpoint::class, ['record' => $endpoint->getKey()])->callAction('expire');

    $refused = lastNotification();

    expect($refused['title'])->toBe('Nothing changed')
        ->and($refused['color'])->toBe('danger')
        ->and($refused['body'])->toContain('no previous secret');

    Livewire::test(ViewEndpoint::class, ['record' => $endpoint->getKey()])->callAction('rotate');
    Livewire::test(ViewEndpoint::class, ['record' => $endpoint->getKey()])->callAction('expire');

    expect(lastNotification()['title'])->toBe('Closed')
        ->and($endpoint->fresh()?->previous_secret_expires_at)->toBeNull();
});

it('retires and reinstates without touching what the endpoint was sent', function (): void {
    $delivery = aDelivery();
    $endpoint = Endpoint::query()->firstOrFail();

    Livewire::test(ViewEndpoint::class, ['record' => $endpoint->getKey()])->callAction('retire');

    expect(lastNotification()['title'])->toBe('Retired')
        ->and($endpoint->fresh()?->isLive())->toBeFalse()
        ->and(EndpointResource::standing($endpoint->fresh() ?? $endpoint))->toBe('Retired')
        ->and($delivery->fresh())->not->toBeNull();

    Livewire::test(ViewEndpoint::class, ['record' => $endpoint->getKey()])->callAction('reinstate');

    expect(lastNotification()['title'])->toBe('Reinstated')
        ->and($endpoint->fresh()?->isLive())->toBeTrue();
});

it('subscribes only to a registered name, and refuses to hold the same one twice', function (): void {
    $endpoint = anEndpoint();
    anEventName();

    expect(ViewEndpoint::nameOptions())->toBe(['order.paid' => 'order.paid']);

    Livewire::test(ViewEndpoint::class, ['record' => $endpoint->getKey()])
        ->callAction('subscribe', ['event_name' => 'order.paid']);

    expect(lastNotification()['title'])->toBe('Subscribed');

    Livewire::test(ViewEndpoint::class, ['record' => $endpoint->getKey()])
        ->callAction('subscribe', ['event_name' => 'order.paid']);

    $again = lastNotification();

    expect($again['title'])->toBe('Nothing changed')
        ->and($again['color'])->toBe('danger')
        ->and($endpoint->subscriptions()->count())->toBe(1);
});

it('unsubscribes from what it holds, and leaves what was already raised standing', function (): void {
    $delivery = aDelivery();
    $endpoint = Endpoint::query()->firstOrFail();

    expect(ViewEndpoint::heldOptions($endpoint))->toBe(['order.paid' => 'order.paid']);

    Livewire::test(ViewEndpoint::class, ['record' => $endpoint->getKey()])
        ->callAction('unsubscribe', ['event_name' => 'order.paid']);

    expect(lastNotification()['title'])->toBe('Unsubscribed')
        ->and($endpoint->subscriptions()->count())->toBe(0)
        ->and($delivery->fresh())->not->toBeNull()
        ->and(ViewEndpoint::heldOptions($endpoint))->toBe([]);
});

it('counts subscriptions and deliveries through both restated relations', function (): void {
    aDelivery();
    $endpoint = EndpointResource::getEloquentQuery()->firstOrFail();

    expect($endpoint->subscriptions_count)->toBe(1)
        ->and($endpoint->deliveries_count)->toBe(1);
});

it('lists this merchant’s endpoints and names the extension through the relation', function (): void {
    $endpoint = anEndpoint();

    Livewire::test(ListEndpoints::class)
        ->assertCanSeeTableRecords(Endpoint::query()->whereKey($endpoint->getKey())->get());

    expect($endpoint->extension()->firstOrFail()->name)->toBe('A Partner');
});

it('shows what one endpoint receives, and nothing another merchant’s does', function (): void {
    // A relation manager reads through the relation, and the relation restates
    // its parent's merchant on top of the foreign key.
    aDelivery();
    aDelivery(tenantId: TestTenant::OTHER);

    $endpoint = Endpoint::query()->where('tenant_id', TestTenant::PRIMARY)->firstOrFail();

    $records = Livewire::test(SubscriptionsRelationManager::class, [
        'ownerRecord' => $endpoint,
        'pageClass' => ViewEndpoint::class,
    ])->instance()->getTable()->getRecords();

    $rows = $records instanceof Collection ? $records : Collection::make($records->items());

    expect($rows)->toHaveCount(1)
        ->and($rows->pluck('tenant_id')->unique()->all())->toBe([TestTenant::PRIMARY]);
});
