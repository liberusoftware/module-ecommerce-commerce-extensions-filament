<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CommerceExtensions\Filament\Resources\Deliveries\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Liberu\Ecommerce\CommerceExtensions\Filament\Concerns\DeniesUnpublishedRelationAbilities;
use Liberu\Ecommerce\CommerceExtensions\Filament\Support\Render;
use Liberu\Ecommerce\CommerceExtensions\Models\Attempt;

/**
 * Every attempt at this delivery, the refused ones included.
 *
 * This is the screen the module exists for, and it answers the question the
 * host's delivery log could not: what did they say. An empty table would read as
 * "nothing to do"; a table of refusals with their reasons reads as "here is the
 * joint that is not attached".
 *
 * A row with an excerpt and no status is a transport that never completed, so
 * its excerpt is an exception message and not a response body. A column labelled
 * "response" would report the one as the other, which is why this one is not.
 */
final class AttemptsRelationManager extends RelationManager
{
    use DeniesUnpublishedRelationAbilities;

    protected static string $relationship = 'attempts';

    protected static ?string $title = 'Attempts, and what came back';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sequence')
                    ->label('Attempt')
                    ->placeholder(Render::NONE)
                    ->tooltip('A refusal has no number, because it takes no slot: binding a transport later must not find the delivery already exhausted.')
                    ->sortable(),
                TextColumn::make('outcome')
                    ->label('Outcome')
                    ->badge()
                    ->state(fn (Attempt $record): string => Render::attemptLabel($record->outcome))
                    ->color(fn (Attempt $record): string => Render::attemptColour($record->outcome)),
                TextColumn::make('attempted_at')->label('Attempted')->dateTime()->sortable(),
                TextColumn::make('settled_at')
                    ->label('Settled')
                    ->dateTime()
                    ->placeholder(Render::NONE)
                    ->tooltip('The row is written before the request and settled after it, so an unsettled row is a worker that died mid-flight rather than an attempt nobody made.'),
                TextColumn::make('refusal_reason')
                    ->label('Refused because')
                    ->state(fn (Attempt $record): ?string => $record->refusal_reason === null
                        ? null
                        : Render::refusal($record->refusal_reason))
                    ->placeholder(Render::NONE)
                    ->wrap(),
                TextColumn::make('response_status')
                    ->label('What came back')
                    ->state(fn (Attempt $record): string => Render::cameBack($record->response_status, $record->response_excerpt))
                    ->wrap(),
                TextColumn::make('duration_ms')
                    ->label('Took')
                    ->state(fn (Attempt $record): string => Render::took($record->response_status, $record->duration_ms))
                    ->wrap()
                    ->tooltip('Measured by the transport, because the module reads no clock of its own — so a transport that never completed has no measurement, whatever is stored beside it.'),
            ])
            ->defaultSort('attempted_at')
            ->recordUrl(null)
            ->recordAction(null)
            ->recordActions([])
            ->headerActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Nothing has been attempted at this delivery yet')
            ->emptyStateDescription('Which is a different fact from an attempt that did not go: every refusal here is a row with a reason on it, so an empty table means nobody has swept this delivery, not that sending failed silently.');
    }
}
