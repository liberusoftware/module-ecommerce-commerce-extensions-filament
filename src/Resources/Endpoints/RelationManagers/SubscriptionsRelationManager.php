<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CommerceExtensions\Filament\Resources\Endpoints\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Liberu\Ecommerce\CommerceExtensions\Filament\Concerns\DeniesUnpublishedRelationAbilities;

/**
 * Which registered event names this endpoint receives.
 *
 * A table and nothing else: subscribing and unsubscribing are published domain
 * actions, and they are on the record's own header where every other action on
 * this endpoint is, rather than split between two places.
 */
final class SubscriptionsRelationManager extends RelationManager
{
    use DeniesUnpublishedRelationAbilities;

    protected static string $relationship = 'subscriptions';

    protected static ?string $title = 'What this endpoint receives';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('event_name')->label('Event name')->sortable(),
                TextColumn::make('created_at')->label('Subscribed')->dateTime()->sortable(),
            ])
            ->defaultSort('event_name')
            ->recordUrl(null)
            ->recordAction(null)
            ->recordActions([])
            ->headerActions([])
            ->toolbarActions([])
            ->emptyStateHeading('This endpoint receives nothing')
            ->emptyStateDescription('An endpoint with no subscription is not an error and not a misconfiguration anybody will hear about: no event names it, so nothing is ever raised for it and no delivery is ever refused.');
    }
}
