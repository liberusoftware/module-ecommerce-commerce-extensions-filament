<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CommerceExtensions\Filament\Resources\EventNames;

use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Liberu\Ecommerce\CommerceExtensions\Filament\Concerns\DeniesUnpublishedResourceAbilities;
use Liberu\Ecommerce\CommerceExtensions\Filament\Resources\EventNames\Pages\ListEventNames;
use Liberu\Ecommerce\CommerceExtensions\Filament\Support\PanelTenant;
use Liberu\Ecommerce\CommerceExtensions\Filament\Support\Render;
use Liberu\Ecommerce\CommerceExtensions\Models\EventName;
use Liberu\Ecommerce\CommerceExtensions\Queries\ListEventNames as ListsEventNames;
use UnitEnum;

/**
 * What may be subscribed to at all, per merchant.
 *
 * The host accepted any string and then matched nothing, so `order.payed` was
 * indistinguishable from an integration that had quietly gone dead for a year.
 * A name is registered here or it is refused at subscription.
 *
 * There is no listing action and no view page: a name is a name, and the domain
 * publishes no verb that retires one.
 */
final class EventNameResource extends Resource
{
    use DeniesUnpublishedResourceAbilities;

    protected static ?string $model = EventName::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $modelLabel = 'event name';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = 'Event names';

    protected static UnitEnum|string|null $navigationGroup = 'Commerce extensions';

    protected static ?int $navigationSort = 30;

    public static function canViewAny(): bool
    {
        return PanelTenant::resolvable();
    }

    /** @return Builder<EventName> */
    public static function getEloquentQuery(): Builder
    {
        return (new ListsEventNames())(PanelTenant::current());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Event name')->searchable()->sortable(),
                TextColumn::make('description')->label('What it means to you')->placeholder(Render::NONE)->wrap(),
                TextColumn::make('created_at')->label('Registered')->dateTime()->sortable(),
            ])
            ->defaultSort('name')
            ->recordUrl(null)
            ->recordAction(null)
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateIcon('heroicon-o-tag')
            ->emptyStateHeading('This merchant has registered no event names')
            ->emptyStateDescription('Until one is registered, no endpoint can subscribe to anything and nothing can be raised. The module never learns what a name means — this registry only says which ones exist.');
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListEventNames::route('/'),
        ];
    }
}
