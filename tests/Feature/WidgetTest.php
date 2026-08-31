<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Liberu\Ecommerce\CommerceExtensions\Enums\AttemptOutcome;
use Liberu\Ecommerce\CommerceExtensions\Enums\RefusalReason;
use Liberu\Ecommerce\CommerceExtensions\Filament\Resources\Deliveries\Pages\ViewDelivery;
use Liberu\Ecommerce\CommerceExtensions\Filament\Support\PanelTenant;
use Liberu\Ecommerce\CommerceExtensions\Filament\Support\Render;
use Liberu\Ecommerce\CommerceExtensions\Filament\Support\Snapshot;
use Liberu\Ecommerce\CommerceExtensions\Filament\Tests\TestTenant;
use Liberu\Ecommerce\CommerceExtensions\Filament\Widgets\DeliveryStanding;
use Liberu\Ecommerce\CommerceExtensions\Filament\Widgets\DueNow;
use Liberu\Ecommerce\CommerceExtensions\Models\Attempt;
use Liberu\Ecommerce\CommerceExtensions\Models\Delivery;
use Livewire\Livewire;

/*
 * The suite the host did not have.
 *
 * `grep -rn 'Widgets\\' tests/` returned nothing across the whole host tree, so
 * `TopProductsWidget` — which raised an unknown-column error for every panel
 * user whose team owned a store — was unreachable from CI. A widget is also the
 * one surface Filament's own tenancy does not reach: `canView()` defaults to
 * true and no panel scope applies to what a widget queries.
 *
 * Every test here constructs a widget.
 */

it('mounts both widgets and renders them', function (): void {
    aDelivery();

    Livewire::test(DeliveryStanding::class)->assertOk();
    Livewire::test(DueNow::class)->assertOk();
});

it('names the unbound transport on a fresh install rather than showing an empty screen', function (): void {
    // The whole point. Nothing carries a signed request until a host binds
    // something, and the honest reading of that is not "nothing to do".
    expect(Livewire::test(DeliveryStanding::class)->instance()->lines())->toBe([
        [
            'label' => 'Something to carry a request',
            'value' => Render::NONE,
            'because' => Render::refusal(RefusalReason::NoTransportBound),
        ],
        [
            'label' => 'Deliveries still owed',
            'value' => '0',
            'because' => 'a delivery is owed until a receiver accepts it, abandoned when its window closes, or redacted by erasure. There is no attempt cap: the window is the single bound',
        ],
    ]);
});

it('calls a transport the container cannot build a misconfiguration, not an unbound seam', function (): void {
    // Telling an operator to bind something that is already configured is the
    // wrong instruction, so this is a third fact rather than an error page.
    Config::set('commerce_extensions.transport', 'Nobody\\Implements\\This');
    Snapshot::forget();

    expect(Snapshot::transportBound())->toBeNull()
        ->and(Livewire::test(DeliveryStanding::class)->instance()->lines()[0])->toBe([
            'label' => 'Something to carry a request',
            'value' => Render::NONE,
            'because' => Render::transport(null),
        ]);
});

it('says a bound transport is bound', function (): void {
    bindTransport();

    expect(Livewire::test(DeliveryStanding::class)->instance()->lines()[0]['value'])->toBe('Bound');
});

it('counts what is owed, and counts each refusal by its reason', function (): void {
    $delivery = aDelivery();

    Livewire::test(ViewDelivery::class, ['record' => $delivery->getKey()])->callAction('attempt');
    Snapshot::forget();

    $lines = Livewire::test(DeliveryStanding::class)->instance()->lines();

    expect($lines[1]['value'])->toBe('1')
        ->and($lines[2])->toBe([
            'label' => 'Refused: '.RefusalReason::NoTransportBound->value,
            'value' => '1',
            'because' => Render::refusal(RefusalReason::NoTransportBound),
        ]);
});

it('counts a settled delivery in its own state and stops counting it as owed', function (): void {
    $delivery = aDelivery();
    bindTransport();

    Livewire::test(ViewDelivery::class, ['record' => $delivery->getKey()])->callAction('attempt');
    Snapshot::forget();

    $lines = Livewire::test(DeliveryStanding::class)->instance()->lines();

    expect($lines[1]['value'])->toBe('0')
        ->and($lines[2]['label'])->toBe('Delivered')
        ->and($lines[2]['value'])->toBe('1');
});

it('names an attempt that was never settled, which is what a killed worker leaves', function (): void {
    $delivery = aDelivery();

    Attempt::query()->create([
        'tenant_id' => TestTenant::PRIMARY,
        'delivery_id' => $delivery->id,
        'sequence' => 1,
        'attempted_at' => at(),
        'outcome' => AttemptOutcome::Pending,
    ]);

    Snapshot::forget();

    expect(Livewire::test(DeliveryStanding::class)->instance()->lines()[2])->toBe([
        'label' => 'Attempts never settled',
        'value' => '1',
        'because' => 'each of these was written before a request and never settled, which is what a worker killed mid-flight leaves behind — evidence that something was attempted, rather than nothing at all',
    ]);
});

it('reads one merchant’s standing where both merchants raised the identical cause', function (): void {
    foreach ([TestTenant::PRIMARY, TestTenant::OTHER] as $tenant) {
        aDelivery(tenantId: $tenant);
    }

    aDelivery(tenantId: TestTenant::OTHER, causeRef: 'order-2');

    expect(Livewire::test(DeliveryStanding::class)->instance()->lines()[1]['value'])->toBe('1');

    TestTenant::use(TestTenant::OTHER);
    Snapshot::forget();

    expect(Livewire::test(DeliveryStanding::class)->instance()->lines()[1]['value'])->toBe('2');
});

it('lists only this merchant’s due deliveries, and gets the right non-zero number', function (): void {
    foreach ([TestTenant::PRIMARY, TestTenant::OTHER] as $tenant) {
        aDelivery(tenantId: $tenant);
    }

    $widget = Livewire::test(DueNow::class);
    $mine = Delivery::query()->where('tenant_id', TestTenant::PRIMARY)->pluck('id')->all();

    expect($widget->instance()->dueIds())->toBe($mine)
        ->and($mine)->toHaveCount(1);

    $widget->assertCanSeeTableRecords(Delivery::query()->whereIn('id', $mine)->get())
        ->assertCanNotSeeTableRecords(Delivery::query()->where('tenant_id', TestTenant::OTHER)->get());
});

it('shows nothing due where every delivery has settled', function (): void {
    // Not the same fact as nothing having been raised, and the empty state says so.
    $delivery = aDelivery();
    bindTransport();

    Livewire::test(ViewDelivery::class, ['record' => $delivery->getKey()])->callAction('attempt');
    Snapshot::forget();

    expect(Livewire::test(DueNow::class)->instance()->dueIds())->toBe([]);
});

it('refuses to render at all when the panel has no merchant', function (): void {
    // `canView()` is true on every Filament widget by default, and a widget
    // carries no panel scope: this is the override that closes it.
    PanelTenant::resolveUsing(fn (): ?string => null);

    expect(DeliveryStanding::canView())->toBeFalse()
        ->and(DueNow::canView())->toBeFalse();

    PanelTenant::resolveUsing(fn (): string => TestTenant::current());

    expect(DeliveryStanding::canView())->toBeTrue()
        ->and(DueNow::canView())->toBeTrue();
});

it('reads the domain once for the whole page rather than once per widget', function (): void {
    aDelivery();

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $first = Snapshot::deliveries(TestTenant::PRIMARY);
    $after = $queries;
    $second = Snapshot::deliveries(TestTenant::PRIMARY);

    expect($after)->toBeGreaterThan(0)
        ->and($queries)->toBe($after)
        ->and($second)->toBe($first)
        ->and(Snapshot::refusals(TestTenant::PRIMARY))->toBe(Snapshot::refusals(TestTenant::PRIMARY))
        ->and(Snapshot::unsettled(TestTenant::PRIMARY))->toBe(0)
        ->and(Snapshot::dueIds(TestTenant::PRIMARY))->toBe(Snapshot::dueIds(TestTenant::PRIMARY));
});

it('reads one clock for the whole request, so two widgets cannot disagree about what is due', function (): void {
    // No action in the domain reads a clock: every one takes the instant it is
    // reasoning about. This is where that instant comes from, once.
    $first = Snapshot::asOf();

    Carbon::setTestNow(Carbon::now()->addHours(3));

    expect(Snapshot::asOf())->toEqual($first);

    Snapshot::forget();

    expect(Snapshot::asOf())->not->toEqual($first);
});
