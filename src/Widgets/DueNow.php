<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CommerceExtensions\Filament\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Liberu\Ecommerce\CommerceExtensions\Filament\Support\PanelTenant;
use Liberu\Ecommerce\CommerceExtensions\Filament\Support\Render;
use Liberu\Ecommerce\CommerceExtensions\Filament\Support\Snapshot;

/**
 * The deliveries the domain says are owed an attempt at this instant.
 *
 * Due is not the same as "will go": the schedule is data on the delivery row and
 * knows nothing about whether a transport is bound. A row here on a fresh
 * install is a delivery that would be attempted and refused, which is why the
 * widget above says which seam is unbound rather than leaving this table to
 * imply it.
 */
class DueNow extends TableWidget
{
    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return PanelTenant::resolvable();
    }

    /**
     * The deliveries the domain counts as due, by key.
     *
     * @return list<int>
     */
    public function dueIds(): array
    {
        return Snapshot::dueIds(PanelTenant::current());
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Owed an attempt right now')
            ->description('Measured against one clock this page read once, so two widgets cannot disagree about whether an attempt has come due. An unsettled delivery always carries a next attempt no later than its own expiry, so the ones due only to be abandoned are here too.')
            ->query(fn (): Builder => Snapshot::due(PanelTenant::current())->withCount('attempts'))
            ->columns([
                TextColumn::make('delivery_ref')->label('Delivery'),
                TextColumn::make('event_name')->label('Event'),
                TextColumn::make('endpoint.url')->label('To')->wrap(),
                TextColumn::make('next_attempt_at')->label('Due')->dateTime(),
                TextColumn::make('expires_at')->label('Window closes')->dateTime(),
                TextColumn::make('attempts_count')->label('Attempts so far')->placeholder(Render::NONE),
            ])
            ->recordUrl(null)
            ->recordAction(null)
            ->recordActions([])
            ->headerActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Nothing is owed an attempt at this instant')
            ->emptyStateDescription('Which is several different facts: nothing raised, every delivery settled, or every one of them waiting out its backoff. The attempts beside each delivery say which.');
    }
}
