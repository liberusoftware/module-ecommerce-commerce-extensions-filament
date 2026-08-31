<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CommerceExtensions\Filament\Resources\Endpoints\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\App;
use Liberu\Ecommerce\CommerceExtensions\Actions\AddEndpoint;
use Liberu\Ecommerce\CommerceExtensions\Exceptions\EndpointIsAlreadyRegistered;
use Liberu\Ecommerce\CommerceExtensions\Exceptions\EndpointUrlIsInvalid;
use Liberu\Ecommerce\CommerceExtensions\Filament\Resources\Endpoints\EndpointResource;
use Liberu\Ecommerce\CommerceExtensions\Filament\Resources\Extensions\ExtensionResource;
use Liberu\Ecommerce\CommerceExtensions\Filament\Support\PanelTenant;
use Liberu\Ecommerce\CommerceExtensions\Filament\Support\Render;
use Liberu\Ecommerce\CommerceExtensions\Filament\Support\Say;
use Liberu\Ecommerce\CommerceExtensions\Queries\ListExtensions;

/**
 * The one screen that issues a secret.
 *
 * `AddEndpoint` returns it once and nothing reads it back, so the notification
 * this raises *is* the secret's existence outside the module. It is persistent
 * for that reason, and there is no redirect: a control that added an endpoint
 * and sent the operator somewhere else would have destroyed the credential it
 * just minted, and the only recovery would be a rotation.
 *
 * The same reasoning is why a duplicate is refused rather than answered with the
 * endpoint that exists — serving a retry would mean handing a one-time secret
 * out twice.
 */
final class ListEndpoints extends ListRecords
{
    protected static string $resource = EndpointResource::class;

    /**
     * Every extension this merchant has, retired ones included.
     *
     * A retired extension is offered because the domain does not refuse an
     * endpoint on one — it refuses the delivery, with a reason. Hiding it here
     * would be this panel restating a rule it does not own.
     *
     * @return array<int, string>
     */
    public static function extensionOptions(): array
    {
        $options = [];

        foreach ((new ListExtensions())(PanelTenant::current())->get() as $extension) {
            $options[$extension->id] = $extension->name.' — '.ExtensionResource::standing($extension);
        }

        return $options;
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('add')
                ->label('Add an endpoint')
                ->icon('heroicon-o-plus')
                ->modalHeading('Give an extension somewhere to receive')
                ->modalDescription('This issues a signing secret and shows it once. Copy it before you dismiss the message: it is encrypted at rest, no screen or query reads it back, and replacing one you did not copy means rotating again.')
                ->modalSubmitActionLabel('Add it and show me the secret')
                ->schema([
                    Select::make('extension')
                        ->label('Extension')
                        ->required()
                        ->options(fn (): array => self::extensionOptions()),
                    TextInput::make('url')
                        ->label('Receives at')
                        ->required()
                        ->maxLength(1024)
                        ->helperText('An https URL that does not resolve into private or reserved address space. The same question is asked again at the moment of sending, because this answer is only true of the DNS that exists now.'),
                ])
                ->action(function (array $data): void {
                    /** @var array{extension: string, url: string} $data */
                    try {
                        $issued = App::make(AddEndpoint::class)(
                            PanelTenant::current(),
                            (int) $data['extension'],
                            $data['url'],
                        );
                    } catch (EndpointUrlIsInvalid $invalid) {
                        Say::it('No endpoint was added, and no secret was issued', 'That URL was refused, because '.Render::refusal($invalid->reason).'.', 'danger');

                        return;
                    } catch (EndpointIsAlreadyRegistered $already) {
                        Say::it(
                            'No endpoint was added, and no secret was issued',
                            $already->getMessage().' A duplicate is refused rather than answered with the endpoint you already have, because answering would mean issuing a second secret for it. Rotate the existing endpoint if you need a new secret.',
                            'danger',
                        );

                        return;
                    }

                    Say::it('Added. Here is the secret, once', Render::secret($issued), 'success');
                }),
        ];
    }
}
