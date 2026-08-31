<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Liberu\Ecommerce\CommerceExtensions\Filament\Resources\Deliveries\DeliveryResource;
use Liberu\Ecommerce\CommerceExtensions\Filament\Resources\Deliveries\Pages\ListDeliveries;
use Liberu\Ecommerce\CommerceExtensions\Filament\Resources\Deliveries\Pages\ViewDelivery;
use Liberu\Ecommerce\CommerceExtensions\Filament\Resources\Deliveries\RelationManagers\AttemptsRelationManager;
use Liberu\Ecommerce\CommerceExtensions\Filament\Resources\Endpoints\EndpointResource;
use Liberu\Ecommerce\CommerceExtensions\Filament\Resources\Endpoints\Pages\ViewEndpoint;
use Liberu\Ecommerce\CommerceExtensions\Filament\Resources\EventNames\EventNameResource;
use Liberu\Ecommerce\CommerceExtensions\Filament\Resources\Extensions\ExtensionResource;
use Liberu\Ecommerce\CommerceExtensions\Filament\Resources\Extensions\Pages\ListExtensions;
use Liberu\Ecommerce\CommerceExtensions\Filament\Resources\Extensions\Pages\ViewExtension;
use Liberu\Ecommerce\CommerceExtensions\Filament\Support\PanelTenant;
use Liberu\Ecommerce\CommerceExtensions\Filament\Support\Snapshot;
use Liberu\Ecommerce\CommerceExtensions\Filament\Tests\TestTenant;
use Liberu\Ecommerce\CommerceExtensions\Models\Attempt;
use Liberu\Ecommerce\CommerceExtensions\Models\Delivery;
use Liberu\Ecommerce\CommerceExtensions\Models\Endpoint;
use Liberu\Ecommerce\CommerceExtensions\Models\Extension;
use Livewire\Livewire;

/*
 * Two merchants with deliberately identical values: the same partner reference,
 * the same receiving URL, the same event name and the same cause reference.
 * Every one of them is the ordinary case — each merchant mints its own — and a
 * proof that creates one merchant's rows proves nothing about a `where` nobody
 * wrote.
 *
 * Every relation on every screen is exercised, not only every list: tenancy has
 * leaked through relations rather than queries in four consecutive waves, and a
 * surface is where `withCount()` gets reached for.
 */

/** @return array{ours: Delivery, theirs: Delivery} */
function twoMerchants(): array
{
    $rows = [];

    foreach ([TestTenant::PRIMARY, TestTenant::OTHER] as $tenant) {
        // The panel is showing whoever it is showing, and an attempt made
        // through it is made as them. Raising one merchant's delivery and then
        // attempting it as the other is not a case this panel can produce.
        TestTenant::use($tenant);

        $delivery = aDelivery(tenantId: $tenant);

        attemptFrom($delivery);

        $rows[] = $delivery;
    }

    TestTenant::reset();
    Snapshot::forget();

    return ['ours' => $rows[0], 'theirs' => $rows[1]];
}

it('lists only this merchant’s rows on every resource', function (): void {
    $rows = twoMerchants();

    Livewire::test(ListDeliveries::class)
        ->assertCanSeeTableRecords(Delivery::query()->whereKey($rows['ours']->getKey())->get())
        ->assertCanNotSeeTableRecords(Delivery::query()->whereKey($rows['theirs']->getKey())->get());

    foreach ([ExtensionResource::class, EndpointResource::class, EventNameResource::class, DeliveryResource::class] as $resource) {
        expect($resource::getEloquentQuery()->pluck('tenant_id')->unique()->all())->toBe([TestTenant::PRIMARY]);
    }
});

it('counts through every restated relation, and gets the right non-zero number', function (): void {
    // `withCount()` builds the relation from a fresh instance whose `tenant_id`
    // is null. Unguarded, the restatement becomes `where('tenant_id', '')` and
    // reports zero for everything, which looks exactly like isolation working.
    twoMerchants();

    $extension = ExtensionResource::getEloquentQuery()->firstOrFail();
    $endpoint = EndpointResource::getEloquentQuery()->firstOrFail();
    $delivery = DeliveryResource::getEloquentQuery()->firstOrFail();

    $mine = Attempt::query()->where('tenant_id', TestTenant::PRIMARY)->count();

    expect($mine)->toBe(1)
        ->and($extension->endpoints_count)->toBe(1)
        ->and($endpoint->subscriptions_count)->toBe(1)
        ->and($endpoint->deliveries_count)->toBe(1)
        ->and($delivery->attempts_count)->toBe($mine);
});

it('shows only this delivery’s attempts through its relation manager', function (): void {
    $rows = twoMerchants();

    $attempts = attemptRows($rows['ours']);

    expect($attempts)->toHaveCount(1)
        ->and($attempts->pluck('tenant_id')->unique()->all())->toBe([TestTenant::PRIMARY]);
});

it('answers another merchant’s record exactly as it answers nobody’s', function (): void {
    // Not a 403 and not a different message: the panel is not a directory of
    // the deployment, so "belongs to somebody else" and "does not exist" are one
    // answer with one exception. Nothing here checks custody before the lookup.
    $rows = twoMerchants();

    $theirEndpoint = Endpoint::query()->where('tenant_id', TestTenant::OTHER)->firstOrFail();
    $theirExtension = Extension::query()->where('tenant_id', TestTenant::OTHER)->firstOrFail();

    $delivery = fn (int|string $key): mixed => Livewire::test(ViewDelivery::class, ['record' => $key]);
    $endpoint = fn (int|string $key): mixed => Livewire::test(ViewEndpoint::class, ['record' => $key]);
    $extension = fn (int|string $key): mixed => Livewire::test(ViewExtension::class, ['record' => $key]);

    expect(fn (): mixed => $delivery($rows['theirs']->getKey()))->toThrow(ModelNotFoundException::class)
        ->and(fn (): mixed => $delivery(99999))->toThrow(ModelNotFoundException::class)
        ->and(fn (): mixed => $endpoint($theirEndpoint->getKey()))->toThrow(ModelNotFoundException::class)
        ->and(fn (): mixed => $endpoint(99999))->toThrow(ModelNotFoundException::class)
        ->and(fn (): mixed => $extension($theirExtension->getKey()))->toThrow(ModelNotFoundException::class)
        ->and(fn (): mixed => $extension(99999))->toThrow(ModelNotFoundException::class);
});

it('keeps a record in one merchant’s hands even where both hold the identical reference', function (): void {
    $rows = twoMerchants();

    $mine = Extension::query()->where('tenant_id', TestTenant::PRIMARY)->firstOrFail();
    $theirs = Extension::query()->where('tenant_id', TestTenant::OTHER)->firstOrFail();
    $theirEndpoint = Endpoint::query()->where('tenant_id', TestTenant::OTHER)->firstOrFail();

    expect($mine->extension_ref)->toBe($theirs->extension_ref)
        ->and($rows['ours']->cause_ref)->toBe($rows['theirs']->cause_ref)
        ->and(ExtensionResource::canView($mine))->toBeTrue()
        ->and(ExtensionResource::canView($theirs))->toBeFalse()
        ->and(EndpointResource::canView($theirEndpoint))->toBeFalse()
        ->and(DeliveryResource::canView($rows['ours']))->toBeTrue()
        ->and(DeliveryResource::canView($rows['theirs']))->toBeFalse();
});

it('follows the panel’s merchant when it changes, rather than the first one it saw', function (): void {
    $rows = twoMerchants();

    TestTenant::use(TestTenant::OTHER);
    Snapshot::forget();

    Livewire::test(ListDeliveries::class)
        ->assertCanSeeTableRecords(Delivery::query()->whereKey($rows['theirs']->getKey())->get())
        ->assertCanNotSeeTableRecords(Delivery::query()->whereKey($rows['ours']->getKey())->get());
});

it('closes every ability the domain does not publish, on resources and on relations', function (): void {
    // A missing policy method is permissive: Filament falls through to
    // `allow()`, so an ability nobody thought about is open unless it is
    // answered. `canAssociate` and `canDissociate` are live on a `hasMany`.
    $rows = twoMerchants();

    $manager = Livewire::test(AttemptsRelationManager::class, [
        'ownerRecord' => $rows['ours'],
        'pageClass' => ViewDelivery::class,
    ])->instance();

    $attempt = Attempt::query()->firstOrFail();

    expect($manager->canViewAny())->toBeTrue()
        ->and($manager->canAssociate())->toBeFalse()
        ->and($manager->canAttach())->toBeFalse()
        ->and($manager->canCreate())->toBeFalse()
        ->and($manager->canDelete($attempt))->toBeFalse()
        ->and($manager->canDeleteAny())->toBeFalse()
        ->and($manager->canDetach($attempt))->toBeFalse()
        ->and($manager->canDetachAny())->toBeFalse()
        ->and($manager->canDissociate($attempt))->toBeFalse()
        ->and($manager->canDissociateAny())->toBeFalse()
        ->and($manager->canEdit($attempt))->toBeFalse()
        ->and($manager->canForceDelete($attempt))->toBeFalse()
        ->and($manager->canForceDeleteAny())->toBeFalse()
        ->and($manager->canReorder())->toBeFalse()
        ->and($manager->canReplicate($attempt))->toBeFalse()
        ->and($manager->canRestore($attempt))->toBeFalse()
        ->and($manager->canRestoreAny())->toBeFalse()
        ->and($manager->canView($attempt))->toBeFalse();

    foreach ([ExtensionResource::class, EndpointResource::class, EventNameResource::class, DeliveryResource::class] as $resource) {
        expect($resource::canCreate())->toBeFalse()
            ->and($resource::canEdit($rows['ours']))->toBeFalse()
            ->and($resource::canDelete($rows['ours']))->toBeFalse()
            ->and($resource::canDeleteAny())->toBeFalse()
            ->and($resource::canForceDelete($rows['ours']))->toBeFalse()
            ->and($resource::canForceDeleteAny())->toBeFalse()
            ->and($resource::canReorder())->toBeFalse()
            ->and($resource::canReplicate($rows['ours']))->toBeFalse()
            ->and($resource::canRestore($rows['ours']))->toBeFalse()
            ->and($resource::canRestoreAny())->toBeFalse()
            ->and($resource::canViewAny())->toBeTrue();
    }
});

it('refuses to resolve a panel with no merchant rather than matching orphan rows', function (): void {
    // `where('tenant_id', null)` compiles to `is null`, which lists exactly the
    // orphan rows a scope exists to hide.
    PanelTenant::resolveUsing(fn (): ?string => null);

    expect(fn (): string => PanelTenant::current())->toThrow(RuntimeException::class)
        ->and(ExtensionResource::canViewAny())->toBeFalse()
        ->and(EndpointResource::canViewAny())->toBeFalse()
        ->and(EventNameResource::canViewAny())->toBeFalse()
        ->and(DeliveryResource::canViewAny())->toBeFalse();

    PanelTenant::resolveUsing(fn (): string => '');

    expect(fn (): string => PanelTenant::current())->toThrow(RuntimeException::class);

    PanelTenant::resolveUsing(fn (): int => 7);

    expect(PanelTenant::current())->toBe('7');
});

it('has no merchant to fall back to when the host names no resolver', function (): void {
    // A panel with no Filament tenancy and no resolver has no merchant to be.
    // There is no "show everything" to fall back to.
    PanelTenant::resolveUsing(null);

    expect(fn (): string => PanelTenant::current())->toThrow(RuntimeException::class);
});

it('will not view a record of the wrong kind, whoever it belongs to', function (): void {
    $rows = twoMerchants();
    $extension = Extension::query()->where('tenant_id', TestTenant::PRIMARY)->firstOrFail();

    expect(ExtensionResource::canView($rows['ours']))->toBeFalse()
        ->and(DeliveryResource::canView($extension))->toBeFalse()
        ->and(EndpointResource::canView($extension))->toBeFalse();
});

it('lists every extension a merchant has, and none of the other merchant’s', function (): void {
    twoMerchants();

    Livewire::test(ListExtensions::class)
        ->assertCanSeeTableRecords(Extension::query()->where('tenant_id', TestTenant::PRIMARY)->get())
        ->assertCanNotSeeTableRecords(Extension::query()->where('tenant_id', TestTenant::OTHER)->get());
});
