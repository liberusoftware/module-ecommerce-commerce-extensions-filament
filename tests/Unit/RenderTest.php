<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Liberu\Ecommerce\CommerceExtensions\Data\AttemptReport;
use Liberu\Ecommerce\CommerceExtensions\Data\IssuedSecret;
use Liberu\Ecommerce\CommerceExtensions\Enums\AttemptOutcome;
use Liberu\Ecommerce\CommerceExtensions\Enums\DeliveryOutcome;
use Liberu\Ecommerce\CommerceExtensions\Enums\RefusalReason;
use Liberu\Ecommerce\CommerceExtensions\Filament\Support\Render;

/*
 * The sentences, with no panel and no database. Every match here is over a
 * domain enum with no default arm, and these walk `cases()`, so a case the
 * domain adds fails this file rather than rendering a blank on a screen.
 */

it('has a sentence for every refusal the domain publishes', function (): void {
    foreach (RefusalReason::cases() as $reason) {
        expect(Render::refusal($reason))->not->toBe('')
            ->and(Render::refusal($reason))->not->toContain('failed');
    }
});

it('separates an unbound transport from one the container cannot build', function (): void {
    // Three facts, and telling an operator to bind something already configured
    // is the wrong instruction.
    expect(Render::transport(true))->toContain('is bound')
        ->and(Render::transport(false))->toBe(Render::refusal(RefusalReason::NoTransportBound))
        ->and(Render::transport(null))->toContain('misconfiguration');
});

it('labels and colours every attempt outcome, including the one a killed worker leaves', function (): void {
    $colours = [];

    foreach (AttemptOutcome::cases() as $outcome) {
        expect(Render::attemptLabel($outcome))->not->toBe('');
        $colours[] = Render::attemptColour($outcome);
    }

    expect(Render::attemptLabel(AttemptOutcome::Pending))->toBe('Never settled')
        // A refusal must not share a colour with a failure: it is not one.
        ->and($colours)->toBe(['gray', 'success', 'danger', 'warning']);
});

it('renders a delivery with no outcome as still owed rather than as an absence', function (): void {
    // Null means not settled yet. An em dash would say there is no outcome to
    // have, which is a different and untrue thing.
    expect(Render::deliveryLabel(null))->toBe('Still owed')
        ->and(Render::deliveryLabel(null))->not->toBe(Render::NONE)
        ->and(Render::deliveryColour(null))->toBe('info');

    foreach (DeliveryOutcome::cases() as $outcome) {
        expect(Render::deliveryLabel($outcome))->not->toBe('Still owed')
            ->and(Render::deliveryColour($outcome))->not->toBe('info');
    }
});

it('says an excerpt with no status is an exception message and not a response', function (): void {
    // The transport threw. Labelling this "response" would report what the
    // sender's own client said as though the receiver had said it.
    expect(Render::cameBack(null, 'Connection refused'))
        ->toContain('never completed')
        ->toContain('Connection refused')
        ->and(Render::cameBack(200, 'thanks'))->toContain('answered 200')
        ->and(Render::cameBack(204, null))->toContain('said nothing')
        ->and(Render::cameBack(null, null))->toBe(Render::NONE);
});

it('renders a redacted subject reference as a fact rather than a blank', function (): void {
    expect(Render::reference(null))->toContain('Nobody named')
        ->and(Render::reference(''))->toContain('Nobody named')
        ->and(Render::reference('person-1'))->toBe('person-1');
});

it('puts the secret itself in the sentence, because nothing reads one back', function (): void {
    $once = new IssuedSecret('tenant-a', 7, 'whsec_abc', null);
    $rotated = new IssuedSecret('tenant-a', 7, 'whsec_def', CarbonImmutable::parse('2026-09-01 12:00:00'));

    expect(Render::secret($once))->toContain('whsec_abc')->toContain('shown once')
        ->and(Render::secret($rotated))->toContain('whsec_def')->toContain('2026-09-01 12:00:00');
});

it('reads a refusal out as a reason, never as a failure and never as zero deliveries', function (): void {
    $refused = new AttemptReport(
        'tenant-a', 1, 4, null, AttemptOutcome::Refused, RefusalReason::NoTransportBound, null, null,
    );

    expect(Render::attempted($refused))
        ->toContain('Nothing went out')
        ->toContain(Render::refusal(RefusalReason::NoTransportBound))
        ->toContain('still owed');
});

it('says a delivery with no outcome on the report is still owed, not finished', function (): void {
    $sent = new AttemptReport(
        'tenant-a', 1, 4, 2, AttemptOutcome::Failed, null, 500, null,
    );

    $settled = new AttemptReport(
        'tenant-a', 1, 4, 1, AttemptOutcome::Delivered, null, 200, DeliveryOutcome::Delivered,
    );

    expect(Render::attempted($sent))->toContain('Attempt 2')->toContain('answered 500')->toContain('still owed')
        ->and(Render::attempted($settled))->toContain('answered 200')->toContain('has settled: Delivered');
});

it('names a transport that never completed even where the report carries no excerpt', function (): void {
    // The concurrent-attempt report is the only one with no attempt id at all.
    $threw = new AttemptReport('tenant-a', 1, 4, 1, AttemptOutcome::Failed, null, null, null);
    $concurrent = new AttemptReport(
        'tenant-a', 1, null, null, AttemptOutcome::Refused, RefusalReason::ConcurrentAttempt, null, null,
    );

    expect(Render::attempted($threw))->toContain('never completed')
        ->and(Render::attempted($concurrent))->toContain('another worker');
});

it('suppresses a duration nobody measured rather than drawing the zero stored beside it', function (): void {
    // `AttemptDelivery` records `duration_ms = 0` when the transport throws — a
    // zero substituted for an unknown, on a nullable column. Drawn as `0 ms` it
    // would read as a request that completed instantly.
    expect(Render::took(null, 0))->toContain('Not measured')
        ->and(Render::took(null, null))->toBe(Render::NONE)
        ->and(Render::took(200, 12))->toBe('12 ms');
});
