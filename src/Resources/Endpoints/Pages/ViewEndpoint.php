<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CommerceExtensions\Filament\Resources\Endpoints\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\App;
use Liberu\Ecommerce\CommerceExtensions\Actions\ExpirePreviousSecret;
use Liberu\Ecommerce\CommerceExtensions\Actions\ReinstateEndpoint;
use Liberu\Ecommerce\CommerceExtensions\Actions\RetireEndpoint;
use Liberu\Ecommerce\CommerceExtensions\Actions\RotateSecret;
use Liberu\Ecommerce\CommerceExtensions\Actions\Subscribe;
use Liberu\Ecommerce\CommerceExtensions\Actions\Unsubscribe;
use Liberu\Ecommerce\CommerceExtensions\Exceptions\SubscriptionIsAlreadyHeld;
use Liberu\Ecommerce\CommerceExtensions\Exceptions\ThereIsNoPreviousSecret;
use Liberu\Ecommerce\CommerceExtensions\Filament\Resources\Endpoints\EndpointResource;
use Liberu\Ecommerce\CommerceExtensions\Filament\Support\PanelTenant;
use Liberu\Ecommerce\CommerceExtensions\Filament\Support\Render;
use Liberu\Ecommerce\CommerceExtensions\Filament\Support\Say;
use Liberu\Ecommerce\CommerceExtensions\Filament\Support\Snapshot;
use Liberu\Ecommerce\CommerceExtensions\Models\Endpoint;
use Liberu\Ecommerce\CommerceExtensions\Queries\ListEventNames;

/**
 * One endpoint, and everything a merchant can do to it.
 *
 * No action carries a `visible()` guard. Filament re-evaluates one against a
 * freshly hydrated record at mount and again at `callMountedAction`, so a guard
 * restating "there is no previous secret" would make the domain's own refusal
 * unreachable, and an unreachable branch cannot be tested.
 */
final class ViewEndpoint extends ViewRecord
{
    protected static string $resource = EndpointResource::class;

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('rotate')
                ->label('Rotate the secret')
                ->icon('heroicon-o-key')
                ->modalHeading('Issue a new signing secret')
                ->modalDescription('The previous secret stays live for a bounded overlap and every request carries a signature under each, so a receiver that has not picked the new one up yet keeps verifying. The new secret is shown once and nothing reads it back.')
                ->modalSubmitActionLabel('Rotate it and show me the secret')
                ->action(function (Endpoint $record): void {
                    $issued = App::make(RotateSecret::class)(
                        PanelTenant::current(),
                        $record->id,
                        Snapshot::asOf(),
                    );

                    Say::it('Rotated. Here is the secret, once', Render::secret($issued), 'success');
                }),

            Action::make('expire')
                ->label('Close the overlap now')
                ->icon('heroicon-o-shield-exclamation')
                ->color('warning')
                ->modalHeading('Expire the previous secret immediately')
                ->modalDescription('For the leak that cannot wait for the window. Every request after this carries one signature, and a receiver still verifying with the old secret stops verifying at once.')
                ->modalSubmitActionLabel('Expire it')
                ->action(function (Endpoint $record): void {
                    try {
                        App::make(ExpirePreviousSecret::class)(PanelTenant::current(), $record->id);
                    } catch (ThereIsNoPreviousSecret $none) {
                        Say::it('Nothing changed', $none->getMessage().' Only one secret is live, so there is no overlap to close.', 'danger');

                        return;
                    }

                    Say::it('Closed', 'The previous secret is expired. Requests carry one signature from now on.', 'success');
                }),

            Action::make('subscribe')
                ->label('Subscribe it to an event name')
                ->icon('heroicon-o-tag')
                ->modalHeading('Subscribe this endpoint to a registered event name')
                ->modalDescription('Only a name this merchant has registered can be subscribed to. The host accepted any string and then matched nothing, so a typo was indistinguishable from an integration that had quietly gone dead.')
                ->modalSubmitActionLabel('Subscribe it')
                ->schema([
                    Select::make('event_name')
                        ->label('Event name')
                        ->required()
                        ->options(fn (): array => self::nameOptions())
                        ->helperText('A name already held is still offered: the domain refuses a duplicate by name, and hiding the option would be this panel restating a rule it does not own.'),
                ])
                ->action(function (Endpoint $record, array $data): void {
                    /** @var array{event_name: string} $data */
                    try {
                        App::make(Subscribe::class)(PanelTenant::current(), $record->id, $data['event_name']);
                    } catch (SubscriptionIsAlreadyHeld $held) {
                        Say::it('Nothing changed', $held->getMessage().' It receives that name once, not twice.', 'danger');

                        return;
                    }

                    Say::it(
                        'Subscribed',
                        'This endpoint now receives '.$data['event_name']
                            .'. Whoever raises that event hands in the bytes; this module never builds a payload and never learns what the name means.',
                        'success',
                    );
                }),

            Action::make('unsubscribe')
                ->label('Stop one of them')
                ->icon('heroicon-o-minus-circle')
                ->color('warning')
                ->modalHeading('Stop this endpoint receiving a name')
                ->modalDescription('Whatever has already been raised for it stands: this stops future events being raised to this endpoint, and does not withdraw a delivery that is owed.')
                ->modalSubmitActionLabel('Unsubscribe it')
                ->schema([
                    Select::make('event_name')
                        ->label('Event name')
                        ->required()
                        ->options(fn (Endpoint $record): array => self::heldOptions($record)),
                ])
                ->action(function (Endpoint $record, array $data): void {
                    /** @var array{event_name: string} $data */
                    App::make(Unsubscribe::class)(PanelTenant::current(), $record->id, $data['event_name']);

                    Say::it(
                        'Unsubscribed',
                        'This endpoint no longer receives '.$data['event_name'].'. Anything already raised for it stands.',
                        'success',
                    );
                }),

            Action::make('retire')
                ->label('Retire it')
                ->icon('heroicon-o-hand-raised')
                ->color('warning')
                ->modalHeading('Retire this endpoint')
                ->modalDescription('Nothing is deleted and nothing cascades. Every delivery this endpoint was ever owed survives, and an attempt on it refuses with a reason rather than failing.')
                ->modalSubmitActionLabel('Retire it')
                ->action(function (Endpoint $record): void {
                    $retired = App::make(RetireEndpoint::class)(
                        PanelTenant::current(),
                        $record->id,
                        Snapshot::asOf(),
                    );

                    Say::it(
                        'Retired',
                        'Nothing more is sent to '.$retired->url.' as of '.((string) $retired->retired_at?->toDateTimeString())
                            .'. Retiring it again keeps that date rather than moving it, and its delivery log is untouched.',
                        'success',
                    );
                }),

            Action::make('reinstate')
                ->label('Reinstate it')
                ->icon('heroicon-o-arrow-path')
                ->modalHeading('Reinstate this endpoint')
                ->modalDescription('The URL is a natural key, so registering the same one again under this extension is refused. Reinstating is how a mis-clicked retirement is undone without issuing a new secret.')
                ->modalSubmitActionLabel('Reinstate it')
                ->action(function (Endpoint $record): void {
                    $reinstated = App::make(ReinstateEndpoint::class)(PanelTenant::current(), $record->id);

                    Say::it(
                        'Reinstated',
                        $reinstated->url.' receives again, under the secret it already had. Deliveries raised while it was retired were refused at the time and are not replayed.',
                        'success',
                    );
                }),
        ];
    }

    /**
     * The merchant's registry, which is the only thing that may be subscribed to.
     *
     * @return array<string, string>
     */
    public static function nameOptions(): array
    {
        $options = [];

        foreach ((new ListEventNames())(PanelTenant::current())->get() as $name) {
            $options[$name->name] = $name->name;
        }

        return $options;
    }

    /**
     * What this endpoint holds now, read through the relation that restates its
     * merchant on top of the foreign key.
     *
     * @return array<string, string>
     */
    public static function heldOptions(Endpoint $record): array
    {
        $options = [];

        foreach ($record->subscriptions()->orderBy('event_name')->get() as $subscription) {
            $options[$subscription->event_name] = $subscription->event_name;
        }

        return $options;
    }
}
