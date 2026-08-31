<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CommerceExtensions\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Liberu\Ecommerce\CommerceExtensions\Enums\DeliveryOutcome;
use Liberu\Ecommerce\CommerceExtensions\Enums\RefusalReason;
use Liberu\Ecommerce\CommerceExtensions\Filament\Support\PanelTenant;
use Liberu\Ecommerce\CommerceExtensions\Filament\Support\Render;
use Liberu\Ecommerce\CommerceExtensions\Filament\Support\Snapshot;

/**
 * Whether anything can go out, on the screen a merchant looks at first.
 *
 * Nothing is bound to carry a signed request on a fresh install, because a
 * package cannot know a host's egress arrangements. An empty screen would read
 * as "nothing to do". This one reads as "here is the joint that is not
 * attached", because a refusal with a reason is the truthful answer and it is
 * not a failure.
 *
 * A widget is the surface Filament's own tenancy does not reach: `canView()`
 * defaults to true and no panel scope applies to what a widget queries. The
 * host's own `TopProductsWidget` raised an unknown-column error for every panel
 * user whose team owned a store and CI never saw it, because
 * `grep -rn 'Widgets\\' tests/` across the whole host tree returned nothing.
 * Every read here states the tenant, and the suite constructs this widget.
 */
class DeliveryStanding extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Whether anything can go out, and what has';

    public static function canView(): bool
    {
        return PanelTenant::resolvable();
    }

    /**
     * What this widget puts on the screen, as data.
     *
     * Public and asserted directly, because the fault it is written against is
     * an empty screen where a refusal belongs, and an assertion that greps
     * rendered markup for an absent nought proves very little.
     *
     * @return list<array{label: string, value: string, because: string}>
     */
    public function lines(): array
    {
        $tenant = PanelTenant::current();
        $bound = Snapshot::transportBound();
        $deliveries = Snapshot::deliveries($tenant);

        $lines = [
            [
                'label' => 'Something to carry a request',
                'value' => $bound === true ? 'Bound' : Render::NONE,
                'because' => Render::transport($bound),
            ],
            [
                'label' => 'Deliveries still owed',
                'value' => (string) $deliveries['owed'],
                'because' => 'a delivery is owed until a receiver accepts it, abandoned when its window closes, or redacted by erasure. There is no attempt cap: the window is the single bound',
            ],
        ];

        foreach (DeliveryOutcome::cases() as $outcome) {
            if ($deliveries[$outcome->value] > 0) {
                $lines[] = [
                    'label' => Render::deliveryLabel($outcome),
                    'value' => (string) $deliveries[$outcome->value],
                    'because' => 'deliveries that have settled in this state and will not be attempted again',
                ];
            }
        }

        $unsettled = Snapshot::unsettled($tenant);

        if ($unsettled > 0) {
            $lines[] = [
                'label' => 'Attempts never settled',
                'value' => (string) $unsettled,
                'because' => 'each of these was written before a request and never settled, which is what a worker killed mid-flight leaves behind — evidence that something was attempted, rather than nothing at all',
            ];
        }

        foreach (Snapshot::refusals($tenant) as $reason => $count) {
            $lines[] = [
                'label' => 'Refused: '.$reason,
                'value' => (string) $count,
                'because' => Render::refusal(RefusalReason::from($reason)),
            ];
        }

        return $lines;
    }

    /** @return array<Stat> */
    protected function getStats(): array
    {
        $stats = [];

        foreach ($this->lines() as $line) {
            $stats[] = Stat::make($line['label'], $line['value'])
                ->description($line['because'])
                ->color($line['value'] === Render::NONE ? 'warning' : 'success');
        }

        return $stats;
    }
}
