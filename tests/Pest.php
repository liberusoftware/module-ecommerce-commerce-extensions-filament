<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Filament\Notifications\Livewire\Notifications;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Liberu\Ecommerce\CommerceExtensions\Actions\AddEndpoint;
use Liberu\Ecommerce\CommerceExtensions\Actions\RaiseEvent;
use Liberu\Ecommerce\CommerceExtensions\Actions\RegisterEventName;
use Liberu\Ecommerce\CommerceExtensions\Actions\RegisterExtension;
use Liberu\Ecommerce\CommerceExtensions\Actions\Subscribe;
use Liberu\Ecommerce\CommerceExtensions\Filament\Resources\Deliveries\Pages\ViewDelivery;
use Liberu\Ecommerce\CommerceExtensions\Filament\Resources\Deliveries\RelationManagers\AttemptsRelationManager;
use Liberu\Ecommerce\CommerceExtensions\Filament\Support\PanelTenant;
use Liberu\Ecommerce\CommerceExtensions\Filament\Support\Snapshot;
use Liberu\Ecommerce\CommerceExtensions\Filament\Tests\Fakes\FakeTransport;
use Liberu\Ecommerce\CommerceExtensions\Filament\Tests\TestCase;
use Liberu\Ecommerce\CommerceExtensions\Filament\Tests\TestTenant;
use Liberu\Ecommerce\CommerceExtensions\Models\Attempt;
use Liberu\Ecommerce\CommerceExtensions\Models\Delivery;
use Liberu\Ecommerce\CommerceExtensions\Models\Endpoint;
use Liberu\Ecommerce\CommerceExtensions\Models\EventName;
use Liberu\Ecommerce\CommerceExtensions\Models\Extension;
use Liberu\Ecommerce\CommerceExtensions\Models\Subscription;
use Livewire\Livewire;

/**
 * `receiver.invalid` never resolves, and the domain lets a name it cannot
 * resolve through on purpose: refusing one would refuse every staging host
 * reachable only from the delivery box. It is the one destination that answers
 * the same way with or without a network.
 */
const RECEIVES_AT = 'https://receiver.invalid/hook';

uses(TestCase::class, RefreshDatabase::class)
    ->beforeEach(function (): void {
        TestTenant::reset();
        PanelTenant::resolveUsing(fn (): string => TestTenant::current());

        // Load-bearing. The transport is unbound as the domain ships it, and
        // that unbound state is what most of this suite claims something about:
        // a test inheriting a binding from the one before it would prove the
        // opposite of what it says.
        Config::set('commerce_extensions.transport', null);

        // The snapshot is one read per request, and a test is not a request.
        Snapshot::forget();

        Carbon::setTestNow(Carbon::parse('2026-08-31 12:00:00'));
    })
    ->in(__DIR__.'/Feature');

// The rendering rules need no database and no panel.
uses(TestCase::class)->in(__DIR__.'/Unit');

function at(string $expression = '2026-08-31 12:00:00'): CarbonImmutable
{
    return CarbonImmutable::parse($expression);
}

function anExtension(string $tenantId = TestTenant::PRIMARY, string $ref = 'partner-1'): Extension
{
    $existing = Extension::query()->where('tenant_id', $tenantId)->where('extension_ref', $ref)->first();

    if ($existing instanceof Extension) {
        return $existing;
    }

    $extension = (new RegisterExtension())($tenantId, $ref, 'A Partner');

    Snapshot::forget();

    return $extension;
}

function anEndpoint(string $tenantId = TestTenant::PRIMARY, string $url = RECEIVES_AT, string $ref = 'partner-1'): Endpoint
{
    $extension = anExtension($tenantId, $ref);

    $existing = Endpoint::query()->where('tenant_id', $tenantId)->where('url', $url)->first();

    if ($existing instanceof Endpoint) {
        return $existing;
    }

    $issued = (new AddEndpoint())($tenantId, $extension->id, $url);

    Snapshot::forget();

    return Endpoint::query()->whereKey($issued->endpointId)->firstOrFail();
}

function anEventName(string $tenantId = TestTenant::PRIMARY, string $name = 'order.paid'): EventName
{
    $existing = EventName::query()->where('tenant_id', $tenantId)->where('name', $name)->first();

    if ($existing instanceof EventName) {
        return $existing;
    }

    $registered = (new RegisterEventName())($tenantId, $name, 'An order was paid for');

    Snapshot::forget();

    return $registered;
}

function aSubscription(string $tenantId = TestTenant::PRIMARY, string $name = 'order.paid', string $url = RECEIVES_AT): Subscription
{
    $endpoint = anEndpoint($tenantId, $url);
    anEventName($tenantId, $name);

    $existing = Subscription::query()
        ->where('tenant_id', $tenantId)
        ->where('endpoint_id', $endpoint->id)
        ->where('event_name', $name)
        ->first();

    if ($existing instanceof Subscription) {
        return $existing;
    }

    $subscription = (new Subscribe())($tenantId, $endpoint->id, $name);

    Snapshot::forget();

    return $subscription;
}

/** One event raised to one subscribed endpoint, which is the shape every delivery screen shows. */
function aDelivery(
    string $tenantId = TestTenant::PRIMARY,
    string $causeRef = 'order-1',
    ?string $subjectRef = 'person-1',
    string $name = 'order.paid',
    string $payload = '{"order":"order-1"}',
    string $raisedAt = '2026-08-31 12:00:00',
): Delivery {
    aSubscription($tenantId, $name);

    $raised = (new RaiseEvent())($tenantId, $name, $causeRef, $subjectRef, $payload, at($raisedAt));

    Snapshot::forget();

    return Delivery::query()->whereKey($raised[0]->deliveryId)->firstOrFail();
}

function bindTransport(?FakeTransport $transport = null): FakeTransport
{
    $transport ??= new FakeTransport();
    Config::set('commerce_extensions.transport', $transport);
    Snapshot::forget();

    return $transport;
}

/**
 * The notifications the last request sent, as title, body and colour.
 *
 * Filament's own `assertNotified()` compares the whole serialised notification,
 * so it fails on an icon this suite has no opinion about. Reading them consumes
 * them, because mounting the component drains the session.
 *
 * @return array{title: ?string, body: ?string, color: mixed}
 */
function lastNotification(): array
{
    $component = new Notifications();
    $component->mount();

    $sent = $component->notifications
        ->map(fn (Notification $notification): array => [
            'title' => $notification->getTitle(),
            'body' => $notification->getBody(),
            'color' => $notification->getColor(),
        ])
        ->values()
        ->all();

    return $sent === [] ? ['title' => null, 'body' => null, 'color' => null] : $sent[count($sent) - 1];
}

/** One attempt at one delivery, made the way an operator makes it. */
function attemptFrom(Delivery $delivery): void
{
    Livewire::test(ViewDelivery::class, ['record' => $delivery->getKey()])->callAction('attempt');
}

/**
 * Every attempt row the relation manager would draw.
 *
 * @return Collection<int, Attempt>
 */
function attemptRows(Delivery $delivery): Collection
{
    $records = Livewire::test(AttemptsRelationManager::class, [
        'ownerRecord' => $delivery,
        'pageClass' => ViewDelivery::class,
    ])->instance()->getTable()->getRecords();

    /** @var Collection<int, Attempt> $rows */
    $rows = $records instanceof Collection ? $records : Collection::make($records->items());

    return $rows;
}
