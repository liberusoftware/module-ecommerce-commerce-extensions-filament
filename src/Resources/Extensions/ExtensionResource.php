<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CommerceExtensions\Filament\Resources\Extensions;

use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Liberu\Ecommerce\CommerceExtensions\Filament\Concerns\DeniesUnpublishedResourceAbilities;
use Liberu\Ecommerce\CommerceExtensions\Filament\Resources\Extensions\Pages\ListExtensions;
use Liberu\Ecommerce\CommerceExtensions\Filament\Resources\Extensions\Pages\ViewExtension;
use Liberu\Ecommerce\CommerceExtensions\Filament\Support\PanelTenant;
use Liberu\Ecommerce\CommerceExtensions\Filament\Support\Render;
use Liberu\Ecommerce\CommerceExtensions\Models\Extension;
use Liberu\Ecommerce\CommerceExtensions\Policies\CustodyPolicy;
use Liberu\Ecommerce\CommerceExtensions\Queries\ListExtensions as ListsExtensions;
use UnitEnum;

/**
 * A merchant's registration of a third party that receives events.
 *
 * A record, not a service provider. Nothing on this screen installs, updates or
 * uninstalls anything, and nothing here boots PHP: `liberusoftware/module-manager`
 * decides which code runs, resolved once from configuration.
 */
final class ExtensionResource extends Resource
{
    use DeniesUnpublishedResourceAbilities;

    protected static ?string $model = Extension::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $modelLabel = 'extension';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static ?string $navigationLabel = 'Extensions';

    protected static UnitEnum|string|null $navigationGroup = 'Commerce extensions';

    protected static ?int $navigationSort = 10;

    public static function canViewAny(): bool
    {
        return PanelTenant::resolvable();
    }

    /** Asked by the row's key rather than by a reference two merchants can both hold. */
    public static function canView(Model $record): bool
    {
        return $record instanceof Extension
            && PanelTenant::resolvable()
            && CustodyPolicy::ownsExtension(PanelTenant::current(), $record->id);
    }

    /** @return Builder<Extension> */
    public static function getEloquentQuery(): Builder
    {
        return (new ListsExtensions())(PanelTenant::current())->withCount('endpoints');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Extension')->searchable()->sortable(),
                TextColumn::make('extension_ref')
                    ->label('Reference')
                    ->searchable()
                    ->tooltip('The merchant\'s own reference for the third party. Opaque: this module never resolves it and joins to no table it does not own.'),
                TextColumn::make('standing')
                    ->label('Standing')
                    ->badge()
                    ->state(fn (Extension $record): string => self::standing($record))
                    ->color(fn (Extension $record): string => $record->isLive() ? 'success' : 'gray'),
                TextColumn::make('endpoints_count')
                    ->label('Endpoints')
                    ->tooltip('Every endpoint this extension holds, retired ones included: a retired endpoint keeps the record of what it was sent.'),
                TextColumn::make('retired_at')->label('Retired')->dateTime()->placeholder(Render::NONE)->sortable(),
            ])
            ->defaultSort('name')
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateIcon('heroicon-o-puzzle-piece')
            ->emptyStateHeading('This merchant has registered nothing')
            ->emptyStateDescription('An extension here is a record of a third party that receives events — a name, a reference and the endpoints it holds. It is not a PHP package and registering one boots no code.');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('The third party')
                ->description('A data record with a tenant, a name and a status. The epic\'s words "install/update/uninstall" describe something this module deliberately does not do.')
                ->columns(3)
                ->schema([
                    TextEntry::make('name')->label('Extension'),
                    TextEntry::make('extension_ref')->label('Reference'),
                    TextEntry::make('standing')
                        ->label('Standing')
                        ->badge()
                        ->state(fn (Extension $record): string => self::standing($record))
                        ->color(fn (Extension $record): string => $record->isLive() ? 'success' : 'gray'),
                    TextEntry::make('retired_at')
                        ->label('Retired')
                        ->dateTime()
                        ->placeholder(Render::NONE)
                        ->helperText('Retiring is a dated fact, not a delete. Every delivery this extension ever received survives it.')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListExtensions::route('/'),
            'view' => ViewExtension::route('/{record}'),
        ];
    }

    public static function standing(Extension $record): string
    {
        return $record->isLive() ? 'Live' : 'Retired';
    }
}
