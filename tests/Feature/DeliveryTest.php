<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Liberu\Ecommerce\CommerceExtensions\Actions\ForgetSubjectAcrossTenants;
use Liberu\Ecommerce\CommerceExtensions\Enums\AttemptOutcome;
use Liberu\Ecommerce\CommerceExtensions\Enums\DeliveryOutcome;
use Liberu\Ecommerce\CommerceExtensions\Enums\RefusalReason;
use Liberu\Ecommerce\CommerceExtensions\Filament\Resources\Deliveries\DeliveryResource;
use Liberu\Ecommerce\CommerceExtensions\Filament\Resources\Deliveries\Pages\ListDeliveries;
use Liberu\Ecommerce\CommerceExtensions\Filament\Resources\Endpoints\Pages\ViewEndpoint;
use Liberu\Ecommerce\CommerceExtensions\Filament\Resources\Extensions\Pages\ViewExtension;
use Liberu\Ecommerce\CommerceExtensions\Filament\Support\Render;
use Liberu\Ecommerce\CommerceExtensions\Filament\Support\Snapshot;
use Liberu\Ecommerce\CommerceExtensions\Filament\Tests\Fakes\FakeTransport;
use Liberu\Ecommerce\CommerceExtensions\Models\Attempt;
use Liberu\Ecommerce\CommerceExtensions\Models\Delivery;
use Liberu\Ecommerce\CommerceExtensions\Models\Endpoint;
use Livewire\Livewire;

it('records a refusal with its reason when nothing is bound to carry the request', function (): void {
    // The decision the whole surface hangs on. Nothing was sent — and what an
    // operator reads is why, not an empty table and not a failure.
    $delivery = aDelivery();

    attemptFrom($delivery);

    $notification = lastNotification();

    expect($notification['title'])->toBe('Nothing went out, and here is why')
        ->and($notification['color'])->toBe('warning')
        ->and($notification['body'])->toContain(Render::refusal(RefusalReason::NoTransportBound))
        ->and($notification['body'])->toContain('still owed')
        ->and($delivery->fresh()?->outcome)->toBeNull();
});

it('says a refusal took no slot, by leaving the attempt without a number', function (): void {
    $delivery = aDelivery();

    attemptFrom($delivery);

    $row = Attempt::query()->firstOrFail();

    expect($row->sequence)->toBeNull()
        ->and($row->outcome)->toBe(AttemptOutcome::Refused)
        ->and(Render::attemptLabel($row->outcome))->toBe('Refused')
        ->and(Render::cameBack($row->response_status, $row->response_excerpt))->toBe(Render::NONE);
});

it('says it went and settles the delivery when a receiver accepts it', function (): void {
    $delivery = aDelivery();
    bindTransport();

    attemptFrom($delivery);

    $notification = lastNotification();

    expect($notification['title'])->toBe('It went, and the receiver accepted it')
        ->and($notification['color'])->toBe('success')
        ->and($notification['body'])->toContain('answered 200')
        ->and($delivery->fresh()?->outcome)->toBe(DeliveryOutcome::Delivered);
});

it('refuses to attempt a settled delivery rather than hiding the button that asks', function (): void {
    // Filament re-evaluates `visible()` at mount and again at
    // `callMountedAction`, so a guard would make this branch unreachable. The
    // guard is dropped; the domain's refusal is caught and read out.
    $delivery = aDelivery();
    bindTransport();

    attemptFrom($delivery);
    attemptFrom($delivery);

    $notification = lastNotification();

    expect($notification['title'])->toBe('Nothing was attempted')
        ->and($notification['color'])->toBe('danger')
        ->and($notification['body'])->toContain('already settled')
        ->and($notification['body'])->toContain('not re-opened')
        ->and(Attempt::query()->count())->toBe(1);
});

it('says a receiver that did not accept it is a delivery still owed', function (): void {
    $delivery = aDelivery();
    bindTransport(new FakeTransport(status: 500, body: 'nope'));

    attemptFrom($delivery);

    $notification = lastNotification();

    expect($notification['title'])->toBe('It went, and the receiver did not accept it')
        ->and($notification['color'])->toBe('danger')
        ->and($notification['body'])->toContain('still owed')
        ->and($delivery->fresh()?->outcome)->toBeNull()
        ->and($delivery->fresh()?->next_attempt_at)->not->toBeNull();
});

it('reads a transport that never completed as an exception message, not as a response', function (): void {
    // An excerpt with no status is the sender's own client raising. A column
    // labelled "response" would report it as something the receiver said.
    $delivery = aDelivery();
    bindTransport(new FakeTransport(failure: new RuntimeException('Connection refused')));

    attemptFrom($delivery);

    $row = Attempt::query()->firstOrFail();

    expect($row->response_status)->toBeNull()
        ->and($row->response_excerpt)->toBe('Connection refused')
        ->and(Render::cameBack($row->response_status, $row->response_excerpt))
        ->toContain('never completed')
        ->and(lastNotification()['body'])->toContain('never completed');
});

it('abandons a delivery whose window has closed, and says which', function (): void {
    $delivery = aDelivery();

    Carbon::setTestNow(Carbon::parse('2026-09-02 12:00:00'));
    Snapshot::forget();

    attemptFrom($delivery);

    $notification = lastNotification();

    expect($notification['body'])->toContain(Render::refusal(RefusalReason::WindowClosed))
        ->and($notification['body'])->toContain('has settled: Abandoned')
        ->and($delivery->fresh()?->outcome)->toBe(DeliveryOutcome::Abandoned);
});

it('names a retired endpoint and a retired extension as the two different refusals they are', function (): void {
    $delivery = aDelivery();
    bindTransport();
    $endpoint = Endpoint::query()->firstOrFail();

    Livewire::test(ViewEndpoint::class, ['record' => $endpoint->getKey()])->callAction('retire');

    attemptFrom($delivery);

    expect(lastNotification()['body'])->toContain(Render::refusal(RefusalReason::EndpointRetired));

    Livewire::test(ViewEndpoint::class, ['record' => $endpoint->getKey()])->callAction('reinstate');
    Livewire::test(ViewExtension::class, ['record' => $endpoint->extension_id])->callAction('retire');

    attemptFrom($delivery);

    expect(lastNotification()['body'])->toContain(Render::refusal(RefusalReason::ExtensionRetired));
});

it('shows every attempt at a delivery, refused ones included', function (): void {
    $delivery = aDelivery();

    attemptFrom($delivery);
    bindTransport(new FakeTransport(status: 500));
    attemptFrom($delivery);

    $rows = attemptRows($delivery);

    expect($rows)->toHaveCount(2)
        ->and(DeliveryResource::getEloquentQuery()->firstOrFail()->attempts_count)->toBe(2);
});

it('renders a delivery with no outcome as still owed rather than as an em dash', function (): void {
    $delivery = aDelivery();

    expect(Render::deliveryLabel($delivery->outcome))->toBe('Still owed');

    Livewire::test(ListDeliveries::class)
        ->assertCanSeeTableRecords(Delivery::query()->whereKey($delivery->getKey())->get());
});

it('keeps the log and loses the bytes when a subject is erased', function (): void {
    // Erasure is not a control this panel carries: it spans merchants and says
    // so in its name. What the panel must do is read the result honestly.
    $delivery = aDelivery();

    (new ForgetSubjectAcrossTenants())('person-1', at());

    $redacted = $delivery->fresh() ?? $delivery;

    expect(DeliveryResource::payload($redacted))->toContain('Nothing is stored')
        ->and(Render::reference($redacted->subject_ref))->toContain('Nobody named')
        ->and(Render::deliveryLabel($redacted->outcome))->toBe('Redacted')
        ->and($redacted->delivery_ref)->toBe($delivery->delivery_ref);
});

it('offers the outcomes and the endpoints the domain publishes, and nothing it invented', function (): void {
    $delivery = aDelivery();
    $endpoint = Endpoint::query()->firstOrFail();

    expect(DeliveryResource::outcomeOptions())->toBe([
        DeliveryOutcome::Delivered->value => 'Delivered',
        DeliveryOutcome::Abandoned->value => 'Abandoned',
        DeliveryOutcome::Redacted->value => 'Redacted',
    ])->and(DeliveryResource::endpointOptions())->toBe([$endpoint->id => RECEIVES_AT])
        ->and(DeliveryResource::payload($delivery))->toBe('{"order":"order-1"}');
});

it('says a transport the container cannot build left no row at all', function (): void {
    // `Seams::transport()` resolves a configured class name outside any try and
    // `AttemptDelivery` does not catch it, so this is the one way an attempt can
    // fail that is an exception rather than a refusal row. Uncaught it would be
    // an error page on a host config typo.
    $delivery = aDelivery();

    Config::set('commerce_extensions.transport', 'Nobody\\Implements\\This');
    Snapshot::forget();

    attemptFrom($delivery);

    $notification = lastNotification();

    expect($notification['title'])->toBe('Nothing was attempted, and no attempt was recorded')
        ->and($notification['body'])->toContain('misconfiguration')
        ->and($notification['body'])->toContain('leaves no row')
        ->and($notification['color'])->toBe('danger')
        ->and(Attempt::query()->count())->toBe(0)
        ->and($delivery->fresh()?->outcome)->toBeNull();
});

it('suppresses the duration on an attempt whose transport never completed', function (): void {
    $delivery = aDelivery();
    bindTransport(new FakeTransport(failure: new RuntimeException('Connection refused')));

    attemptFrom($delivery);

    $row = Attempt::query()->firstOrFail();

    expect($row->duration_ms)->toBe(0)
        ->and(Render::took($row->response_status, $row->duration_ms))->toContain('Not measured')
        ->and(attemptRows($delivery))->toHaveCount(1);
});
