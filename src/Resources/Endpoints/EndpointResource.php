<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CommerceExtensions\Filament\Resources\Endpoints;

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
use Liberu\Ecommerce\CommerceExtensions\Filament\Resources\Endpoints\Pages\ListEndpoints;
use Liberu\Ecommerce\CommerceExtensions\Filament\Resources\Endpoints\Pages\ViewEndpoint;
use Liberu\Ecommerce\CommerceExtensions\Filament\Resources\Endpoints\RelationManagers\SubscriptionsRelationManager;
use Liberu\Ecommerce\CommerceExtensions\Filament\Support\PanelTenant;
use Liberu\Ecommerce\CommerceExtensions\Filament\Support\Render;
use Liberu\Ecommerce\CommerceExtensions\Filament\Support\Snapshot;
use Liberu\Ecommerce\CommerceExtensions\Models\Endpoint;
use Liberu\Ecommerce\CommerceExtensions\Policies\CustodyPolicy;
use Liberu\Ecommerce\CommerceExtensions\Queries\ListEndpoints as ListsEndpoints;
use UnitEnum;

/**
 * One URL an extension receives at, and the state of the secrets its requests
 * are signed with.
 *
 * No secret appears on this screen. Both columns are encrypted and hidden and
 * nothing reads one back: a secret exists outside the module for exactly as long
 * as the notification that issued it is on somebody's screen, and the only
 * recovery is another rotation. What is shown here is whether an overlap is
 * running, which is a date and not a credential.
 */
final class EndpointResource extends Resource
{
    use DeniesUnpublishedResourceAbilities;

    protected static ?string $model = Endpoint::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $modelLabel = 'endpoint';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-signal';

    protected static ?string $navigationLabel = 'Endpoints';

    protected static UnitEnum|string|null $navigationGroup = 'Commerce extensions';

    protected static ?int $navigationSort = 20;

    public static function canViewAny(): bool
    {
        return PanelTenant::resolvable();
    }

    public static function canView(Model $record): bool
    {
        return $record instanceof Endpoint
            && PanelTenant::resolvable()
            && CustodyPolicy::ownsEndpoint(PanelTenant::current(), $record->id);
    }

    /** @return Builder<Endpoint> */
    public static function getEloquentQuery(): Builder
    {
        return (new ListsEndpoints())(PanelTenant::current())
            ->withCount(['subscriptions', 'deliveries']);
    }

    /** @return array<class-string> */
    public static function getRelations(): array
    {
        return [SubscriptionsRelationManager::class];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('url')->label('Receives at')->searchable()->wrap(),
                TextColumn::make('extension.name')
                    ->label('Extension')
                    ->tooltip('Read through a relation that restates this endpoint\'s merchant on top of the foreign key.'),
                TextColumn::make('standing')
                    ->label('Standing')
                    ->badge()
                    ->state(fn (Endpoint $record): string => self::standing($record))
                    ->color(fn (Endpoint $record): string => $record->isLive() ? 'success' : 'gray'),
                TextColumn::make('subscriptions_count')
                    ->label('Subscribed to')
                    ->tooltip('How many registered event names this endpoint receives. Nothing is sent for a name it does not hold.'),
                TextColumn::make('deliveries_count')
                    ->label('Deliveries')
                    ->tooltip('Every event ever owed to this endpoint. The log outlives the endpoint, so retiring one keeps all of them.'),
                TextColumn::make('rotation')
                    ->label('Rotation overlap')
                    ->state(fn (Endpoint $record): string => self::rotation($record))
                    ->wrap(),
            ])
            ->defaultSort('id')
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateIcon('heroicon-o-signal')
            ->emptyStateHeading('No extension of this merchant has anywhere to receive')
            ->emptyStateDescription('Adding an endpoint issues a signing secret, and that is the one moment it exists outside this module: it is shown once, encrypted at rest, and nothing can read it back.');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Where it receives')
                ->columns(3)
                ->schema([
                    TextEntry::make('url')->label('Receives at')->columnSpanFull(),
                    TextEntry::make('extension.name')->label('Extension'),
                    TextEntry::make('standing')
                        ->label('Standing')
                        ->badge()
                        ->state(fn (Endpoint $record): string => self::standing($record))
                        ->color(fn (Endpoint $record): string => $record->isLive() ? 'success' : 'gray'),
                    TextEntry::make('retired_at')->label('Retired')->dateTime()->placeholder(Render::NONE),
                ]),

            Section::make('The signing secret')
                ->description('Encrypted at rest and returned exactly once, at issue. There is no screen, query or event that reads one back, so a secret nobody copied is replaced by rotating again rather than by looking it up.')
                ->columns(2)
                ->schema([
                    TextEntry::make('rotation')
                        ->label('Rotation overlap')
                        ->state(fn (Endpoint $record): string => self::rotation($record))
                        ->columnSpanFull(),
                    TextEntry::make('signature')
                        ->label('What a receiver verifies')
                        ->state('Each request carries X-Liberu-Timestamp and X-Liberu-Signature, and the signed material is the timestamp, a full stop, and the body — so a captured request is not replayable forever. During an overlap the header carries one signature per live secret, comma separated, and a receiver that verifies either is verified. X-Liberu-Delivery is stable across every retry of the same event, so a receiver can deduplicate.')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListEndpoints::route('/'),
            'view' => ViewEndpoint::route('/{record}'),
        ];
    }

    public static function standing(Endpoint $record): string
    {
        return $record->isLive() ? 'Live' : 'Retired';
    }

    /**
     * Whether a receiver may still be verifying under the previous secret.
     *
     * Measured against the one clock this page read, so two screens cannot
     * disagree about whether an overlap has closed.
     */
    public static function rotation(Endpoint $record): string
    {
        $expiresAt = $record->previous_secret_expires_at;

        if ($expiresAt === null) {
            return 'One secret is live. Requests carry a single signature.';
        }

        return $expiresAt->greaterThan(Snapshot::asOf())
            ? 'The previous secret is live until '.$expiresAt->toDateTimeString()
                .', so every request carries a signature under each and a receiver still on the old one keeps verifying.'
            : 'The overlap closed at '.$expiresAt->toDateTimeString().'. Only the current secret signs.';
    }
}
