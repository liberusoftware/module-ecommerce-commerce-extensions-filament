<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CommerceExtensions\Filament\Resources\Deliveries;

use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Liberu\Ecommerce\CommerceExtensions\Enums\DeliveryOutcome;
use Liberu\Ecommerce\CommerceExtensions\Filament\Concerns\DeniesUnpublishedResourceAbilities;
use Liberu\Ecommerce\CommerceExtensions\Filament\Resources\Deliveries\Pages\ListDeliveries;
use Liberu\Ecommerce\CommerceExtensions\Filament\Resources\Deliveries\Pages\ViewDelivery;
use Liberu\Ecommerce\CommerceExtensions\Filament\Resources\Deliveries\RelationManagers\AttemptsRelationManager;
use Liberu\Ecommerce\CommerceExtensions\Filament\Support\PanelTenant;
use Liberu\Ecommerce\CommerceExtensions\Filament\Support\Render;
use Liberu\Ecommerce\CommerceExtensions\Models\Delivery;
use Liberu\Ecommerce\CommerceExtensions\Policies\CustodyPolicy;
use Liberu\Ecommerce\CommerceExtensions\Queries\ListDeliveries as ListsDeliveries;
use Liberu\Ecommerce\CommerceExtensions\Queries\ListEndpoints;
use UnitEnum;

/**
 * One event owed to one endpoint, and every attempt made at it.
 *
 * The log outlives the endpoint: nothing cascades, nothing is deleted except by
 * the retention command, and retiring an endpoint keeps every row. The host
 * cascaded deliveries off the endpoint, so deleting a misconfigured URL
 * destroyed the security log that would have said what it had been sent.
 *
 * No subject reference appears on the listing.
 */
final class DeliveryResource extends Resource
{
    use DeniesUnpublishedResourceAbilities;

    protected static ?string $model = Delivery::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $modelLabel = 'delivery';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-paper-airplane';

    protected static ?string $navigationLabel = 'Deliveries';

    protected static UnitEnum|string|null $navigationGroup = 'Commerce extensions';

    protected static ?int $navigationSort = 40;

    public static function canViewAny(): bool
    {
        return PanelTenant::resolvable();
    }

    public static function canView(Model $record): bool
    {
        return $record instanceof Delivery
            && PanelTenant::resolvable()
            && CustodyPolicy::ownsDelivery(PanelTenant::current(), $record->id);
    }

    /** @return Builder<Delivery> */
    public static function getEloquentQuery(): Builder
    {
        return (new ListsDeliveries())(PanelTenant::current())->withCount('attempts');
    }

    /** @return array<class-string> */
    public static function getRelations(): array
    {
        return [AttemptsRelationManager::class];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('delivery_ref')
                    ->label('Delivery')
                    ->searchable()
                    ->tooltip('What the receiver sees in X-Liberu-Delivery. It is stable across every retry of this event, so a receiver can deduplicate the retries a sender is guaranteed to produce.'),
                TextColumn::make('event_name')->label('Event')->searchable()->sortable(),
                TextColumn::make('endpoint.url')->label('To')->wrap(),
                TextColumn::make('outcome')
                    ->label('Where it stands')
                    ->badge()
                    ->state(fn (Delivery $record): string => Render::deliveryLabel($record->outcome))
                    ->color(fn (Delivery $record): string => Render::deliveryColour($record->outcome))
                    ->tooltip('No outcome means the delivery is still owed, not that nothing came of it. There is no attempt cap: the window is the single bound, and a retry that would fall outside it abandons instead.')
                    ->sortable(),
                TextColumn::make('attempts_count')
                    ->label('Attempts')
                    ->tooltip('Refusals included. A refusal is a row with a reason on it and takes no slot.'),
                TextColumn::make('raised_at')->label('Raised')->dateTime()->sortable(),
                TextColumn::make('next_attempt_at')
                    ->label('Next attempt')
                    ->dateTime()
                    ->placeholder(Render::NONE)
                    ->sortable(),
                TextColumn::make('expires_at')->label('Window closes')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('outcome')
                    ->label('Where it stands')
                    ->options(fn (): array => self::outcomeOptions()),
                SelectFilter::make('endpoint_id')
                    ->label('To')
                    ->options(fn (): array => self::endpointOptions()),
            ])
            ->defaultSort('raised_at', 'desc')
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateIcon('heroicon-o-paper-airplane')
            ->emptyStateHeading('Nothing has been raised for this merchant')
            ->emptyStateDescription('This module raises nothing of its own. Whoever owns the event hands in a name, a cause, a subject and already-serialised bytes; it never builds a payload, never reads a domain model and never touches money.');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('What is owed, and to whom')
                ->columns(3)
                ->schema([
                    TextEntry::make('delivery_ref')->label('Delivery reference'),
                    TextEntry::make('event_name')->label('Event'),
                    TextEntry::make('endpoint.url')->label('To'),
                    TextEntry::make('cause_ref')
                        ->label('Caused by')
                        ->helperText('The caller\'s reference for the occurrence. It is the natural key: raising the same cause for the same subject returns this delivery rather than sending the event twice, and raising it for a different subject is refused rather than answered with this row.'),
                    TextEntry::make('subject_ref')
                        ->label('About')
                        ->state(fn (Delivery $record): string => Render::reference($record->subject_ref)),
                    TextEntry::make('outcome')
                        ->label('Where it stands')
                        ->badge()
                        ->state(fn (Delivery $record): string => Render::deliveryLabel($record->outcome))
                        ->color(fn (Delivery $record): string => Render::deliveryColour($record->outcome)),
                ]),

            Section::make('The bytes')
                ->description('Stored once and sent unchanged by every attempt, so a retry cannot deliver a different fact than the one that was raised. Erasure redacts them and keeps every attempt, its status, its timing and its outcome.')
                ->schema([
                    TextEntry::make('payload')
                        ->hiddenLabel()
                        ->state(fn (Delivery $record): string => self::payload($record))
                        ->columnSpanFull(),
                ]),

            Section::make('The schedule')
                ->columns(4)
                ->schema([
                    TextEntry::make('raised_at')->label('Raised')->dateTime(),
                    TextEntry::make('next_attempt_at')->label('Next attempt')->dateTime()->placeholder(Render::NONE),
                    TextEntry::make('expires_at')
                        ->label('Window closes')
                        ->dateTime()
                        ->helperText('The single bound. There is no attempt cap, so a receiver reading the headers has one number to reason about.'),
                    TextEntry::make('settled_at')->label('Settled')->dateTime()->placeholder(Render::NONE),
                    TextEntry::make('redacted_at')
                        ->label('Redacted')
                        ->dateTime()
                        ->placeholder(Render::NONE)
                        ->helperText('Erasure keeps the arithmetic. A delivery still owed when erasure reaches it settles, because there is nothing left to send.')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListDeliveries::route('/'),
            'view' => ViewDelivery::route('/{record}'),
        ];
    }

    /** @return array<string, string> */
    public static function outcomeOptions(): array
    {
        $options = [];

        foreach (DeliveryOutcome::cases() as $outcome) {
            $options[$outcome->value] = Render::deliveryLabel($outcome);
        }

        return $options;
    }

    /** @return array<int, string> */
    public static function endpointOptions(): array
    {
        $options = [];

        foreach ((new ListEndpoints())(PanelTenant::current())->get() as $endpoint) {
            $options[$endpoint->id] = $endpoint->url;
        }

        return $options;
    }

    /** Redacted is a fact, and so is a caller who sent an empty body. */
    public static function payload(Delivery $record): string
    {
        $payload = $record->payload ?? '';

        if ($payload === '') {
            return 'Nothing is stored. Either erasure redacted it, or the caller raised this event with no body at all — and an attempt on a redacted delivery cannot send what is no longer there.';
        }

        return $payload;
    }
}
