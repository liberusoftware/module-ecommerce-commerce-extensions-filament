<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CommerceExtensions\Filament\Support;

use Liberu\Ecommerce\CommerceExtensions\Data\AttemptReport;
use Liberu\Ecommerce\CommerceExtensions\Data\IssuedSecret;
use Liberu\Ecommerce\CommerceExtensions\Enums\AttemptOutcome;
use Liberu\Ecommerce\CommerceExtensions\Enums\DeliveryOutcome;
use Liberu\Ecommerce\CommerceExtensions\Enums\RefusalReason;

/**
 * The sentences this panel is most likely to get wrong, written once.
 *
 * Nothing here invents a figure and nothing renders a refusal as a failure. The
 * two nulls this domain publishes are facts rather than absences: a delivery
 * with no outcome is still owed, and an attempt with an excerpt but no status is
 * a transport that never completed, so its excerpt is an exception message and
 * not something a receiver said.
 *
 * Every `match` is over a domain enum with no default arm, so a case the domain
 * adds is a hole here rather than a blank on a screen, and `RenderTest` walks
 * `cases()`.
 */
final class Render
{
    public const NONE = '—';

    /** Why nothing went out. Never a failure, and never zero deliveries. */
    public static function refusal(RefusalReason $reason): string
    {
        return match ($reason) {
            RefusalReason::NoTransportBound => 'nothing is bound to carry a signed request, so nobody was asked to deliver it — which is not a failure to deliver, it is not having anything to deliver through; the refusal takes no slot, so binding a transport later does not find the delivery exhausted',
            RefusalReason::ExtensionRetired => 'the extension this endpoint belongs to has been retired, so a merchant has silenced the partner without deleting what it received',
            RefusalReason::EndpointRetired => 'this endpoint has been retired, so nothing is sent to it and the record of what it was sent survives',
            RefusalReason::DestinationIsNotHttps => 'the URL does not name an https host, and a signed request carrying a merchant\'s payload does not go out in the clear',
            RefusalReason::DestinationIsNotPublic => 'the URL resolves into private or reserved address space at this moment, whatever it resolved to when it was registered — the check at registration is only true of the DNS that existed then',
            RefusalReason::ConcurrentAttempt => 'another worker holds this attempt number and owns the reschedule, so the index arbitrated rather than letting both send; this is the one refusal that writes no attempt row',
            RefusalReason::WindowClosed => 'the delivery window has closed, so there is no attempt left to make and the delivery is abandoned rather than retried forever',
        };
    }

    /** What is at the end of the transport seam. Null is configured and unbuildable, not unbound. */
    public static function transport(?bool $bound): string
    {
        return match ($bound) {
            true => 'something is bound to carry a signed request, so a due delivery is attempted rather than refused',
            false => self::refusal(RefusalReason::NoTransportBound),
            null => 'something is configured to carry a signed request and the container cannot build it, so this is a misconfiguration rather than an unbound seam, and every attempt on it will refuse',
        };
    }

    public static function attemptLabel(AttemptOutcome $outcome): string
    {
        return match ($outcome) {
            AttemptOutcome::Pending => 'Never settled',
            AttemptOutcome::Delivered => 'Delivered',
            AttemptOutcome::Failed => 'Not accepted',
            AttemptOutcome::Refused => 'Refused',
        };
    }

    /** Four states and four colours: a refusal is not a failure and neither is an unsettled row. */
    public static function attemptColour(AttemptOutcome $outcome): string
    {
        return match ($outcome) {
            AttemptOutcome::Pending => 'gray',
            AttemptOutcome::Delivered => 'success',
            AttemptOutcome::Failed => 'danger',
            AttemptOutcome::Refused => 'warning',
        };
    }

    /** Null means still owed. It does not mean no outcome, and it is not an em dash. */
    public static function deliveryLabel(?DeliveryOutcome $outcome): string
    {
        return match ($outcome) {
            null => 'Still owed',
            DeliveryOutcome::Delivered => 'Delivered',
            DeliveryOutcome::Abandoned => 'Abandoned',
            DeliveryOutcome::Redacted => 'Redacted',
        };
    }

    public static function deliveryColour(?DeliveryOutcome $outcome): string
    {
        return match ($outcome) {
            null => 'info',
            DeliveryOutcome::Delivered => 'success',
            DeliveryOutcome::Abandoned => 'danger',
            DeliveryOutcome::Redacted => 'gray',
        };
    }

    /**
     * What came back from one attempt.
     *
     * An excerpt with no status is a transport that never completed, so the
     * excerpt is an exception message rather than a response body. A column
     * labelled "response" would report an exception as though a receiver had
     * said it.
     */
    public static function cameBack(?int $status, ?string $excerpt): string
    {
        if ($status !== null) {
            return $excerpt === null
                ? 'The receiver answered '.$status.' and said nothing.'
                : 'The receiver answered '.$status.': '.$excerpt;
        }

        if ($excerpt !== null) {
            return 'The transport never completed, so nothing was received and this is its exception message rather than anything a receiver said: '.$excerpt;
        }

        return self::NONE;
    }

    /**
     * How long an attempt took, where anybody measured it.
     *
     * A transport that never completed is recorded with a duration of zero,
     * which is a substitution for a number nobody observed. It is suppressed
     * alongside the null status rather than drawn as `0 ms`, because a zero on a
     * nullable column is the one figure this fleet does not render.
     */
    public static function took(?int $status, ?int $durationMs): string
    {
        if ($durationMs === null) {
            return self::NONE;
        }

        return $status === null
            ? 'Not measured, because the transport never completed. The zero stored beside it is a substitution rather than an elapsed time anybody observed.'
            : $durationMs.' ms';
    }

    /** A subject reference the erasure path has redacted, which is a fact rather than a blank. */
    public static function reference(?string $reference): string
    {
        return ($reference ?? '') === ''
            ? 'Nobody named — either the caller named no subject, or the reference has been redacted'
            : (string) $reference;
    }

    /**
     * The one moment a secret exists outside the module.
     *
     * Both columns are encrypted and hidden and no query reads one back, so a
     * screen that issues a secret and does not put it in front of somebody has
     * destroyed it, and the only recovery is another rotation.
     */
    public static function secret(IssuedSecret $issued): string
    {
        $overlap = $issued->previousSecretExpiresAt;

        return 'Copy this now — it is shown once and nothing can read it back: '.$issued->secret.'. '
            .($overlap === null
                ? 'Sign every request you receive at this endpoint with it.'
                : 'The previous secret stays live until '.$overlap->toDateTimeString()
                    .', and until then every request carries a signature under each, so a receiver still on the old one keeps verifying.');
    }

    /** What one attempt did, never a bare "done" and never a refusal reported as a failure. */
    public static function attempted(AttemptReport $report): string
    {
        $reason = $report->refusalReason;

        if ($reason !== null) {
            return 'Nothing went out, because '.self::refusal($reason).' '.self::stands($report);
        }

        $which = $report->sequence === null ? 'The attempt' : 'Attempt '.$report->sequence;
        $status = $report->responseStatus;

        return $which.' went out. '
            .($status === null
                ? 'The transport never completed, so nothing was received and the attempt row carries its exception message rather than a response.'
                : 'The receiver answered '.$status.'.')
            .' '.self::stands($report);
    }

    private static function stands(AttemptReport $report): string
    {
        return $report->deliveryOutcome === null
            ? 'The delivery is still owed and another attempt is scheduled inside its window.'
            : 'The delivery has settled: '.self::deliveryLabel($report->deliveryOutcome).'.';
    }
}
