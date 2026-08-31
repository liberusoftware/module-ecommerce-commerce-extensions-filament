<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CommerceExtensions\Filament\Support;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Liberu\Ecommerce\CommerceExtensions\Enums\AttemptOutcome;
use Liberu\Ecommerce\CommerceExtensions\Enums\DeliveryOutcome;
use Liberu\Ecommerce\CommerceExtensions\Enums\RefusalReason;
use Liberu\Ecommerce\CommerceExtensions\Models\Attempt;
use Liberu\Ecommerce\CommerceExtensions\Models\Delivery;
use Liberu\Ecommerce\CommerceExtensions\Queries\ListDeliveries;
use Liberu\Ecommerce\CommerceExtensions\Queries\ListDueDeliveries;
use Liberu\Ecommerce\CommerceExtensions\Support\Seams;
use Throwable;

/**
 * One read of the domain per request, shared by everything on the page.
 *
 * The domain reads no clock: every action takes the instant it is reasoning
 * about. This is where that instant comes from, once, so a page rendered across
 * a minute boundary cannot show one widget calling a delivery due and another
 * calling its window closed.
 *
 * Every figure here is a count over an indexed, tenant-scoped query. The host's
 * equivalent loaded every delivery in a 24-hour window into memory and grouped
 * in PHP, which is the fault this module was extracted over; a widget that did
 * the same thing on a panel would be the same fault on a different screen.
 *
 * ponytail: a process-static memo, which is one request under FPM. Reset it in a
 * boot hook if this ever runs under a persistent worker.
 */
final class Snapshot
{
    private static ?CarbonImmutable $asOf = null;

    /** @var array<string, array<string, int>> */
    private static array $deliveries = [];

    /** @var array<string, array<string, int>> */
    private static array $refusals = [];

    /** @var array<string, int> */
    private static array $unsettled = [];

    /** @var array<string, list<int>> */
    private static array $due = [];

    /** The one clock this package reads, and the only place it is read. */
    public static function asOf(): CarbonImmutable
    {
        return self::$asOf ??= CarbonImmutable::now();
    }

    /**
     * Whether anything is bound to carry a signed request.
     *
     * Three facts, not two: null is a class name the container cannot build,
     * which is a misconfiguration and not an unbound seam — telling an operator
     * to bind something already configured is the wrong instruction.
     */
    public static function transportBound(): ?bool
    {
        try {
            return Seams::transport() !== null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * How many deliveries stand in each terminal state, and how many are still
     * owed. Owed is the total less the settled, so it uses the published query
     * rather than a `where` this panel invented.
     *
     * @return array<string, int>
     */
    public static function deliveries(string $tenantId): array
    {
        if (array_key_exists($tenantId, self::$deliveries)) {
            return self::$deliveries[$tenantId];
        }

        $list = new ListDeliveries();
        $counts = [];
        $settled = 0;

        foreach (DeliveryOutcome::cases() as $outcome) {
            $counts[$outcome->value] = $list($tenantId, null, $outcome)->count();
            $settled += $counts[$outcome->value];
        }

        $counts['owed'] = $list($tenantId)->count() - $settled;

        return self::$deliveries[$tenantId] = $counts;
    }

    /**
     * Why nothing went out, counted by reason.
     *
     * The domain publishes no query over attempts at all, so this reads the
     * model. `docs/panel.md` records the gap.
     *
     * @return array<string, int>
     */
    public static function refusals(string $tenantId): array
    {
        if (array_key_exists($tenantId, self::$refusals)) {
            return self::$refusals[$tenantId];
        }

        $counts = [];

        foreach (RefusalReason::cases() as $reason) {
            $count = Attempt::query()
                ->where('tenant_id', $tenantId)
                ->where('refusal_reason', $reason->value)
                ->count();

            if ($count > 0) {
                $counts[$reason->value] = $count;
            }
        }

        return self::$refusals[$tenantId] = $counts;
    }

    /** Attempts written before a request and never settled, which is what a killed worker leaves. */
    public static function unsettled(string $tenantId): int
    {
        return self::$unsettled[$tenantId] ??= Attempt::query()
            ->where('tenant_id', $tenantId)
            ->where('outcome', AttemptOutcome::Pending->value)
            ->count();
    }

    /**
     * The deliveries the domain says are owed an attempt at this instant.
     *
     * A Builder rather than a list, because that is what the domain publishes
     * and what a table paginates. It is not memoised: a builder is mutable and a
     * shared one would carry the last caller's `where` into the next.
     *
     * @return Builder<Delivery>
     */
    public static function due(string $tenantId): Builder
    {
        return (new ListDueDeliveries())($tenantId, self::asOf());
    }

    /**
     * The same deliveries, by key.
     *
     * `ListDueDeliveries` carries a limit, which is a worker's batch bound: a
     * paginated table supersedes it and this accessor does not, so this can only
     * ever be a prefix of what the table draws. Nothing on any screen renders a
     * count of what is due, so there is no figure for the two to disagree about.
     *
     * @return list<int>
     */
    public static function dueIds(string $tenantId): array
    {
        if (array_key_exists($tenantId, self::$due)) {
            return self::$due[$tenantId];
        }

        /** @var list<int> $ids */
        $ids = self::due($tenantId)->pluck('id')->all();

        return self::$due[$tenantId] = $ids;
    }

    public static function forget(): void
    {
        self::$asOf = null;
        self::$deliveries = [];
        self::$refusals = [];
        self::$unsettled = [];
        self::$due = [];
    }
}
