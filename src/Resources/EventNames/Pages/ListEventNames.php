<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CommerceExtensions\Filament\Resources\EventNames\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\App;
use Liberu\Ecommerce\CommerceExtensions\Actions\RegisterEventName;
use Liberu\Ecommerce\CommerceExtensions\Exceptions\EventNameIsAlreadyRegistered;
use Liberu\Ecommerce\CommerceExtensions\Filament\Resources\EventNames\EventNameResource;
use Liberu\Ecommerce\CommerceExtensions\Filament\Support\PanelTenant;
use Liberu\Ecommerce\CommerceExtensions\Filament\Support\Say;

final class ListEventNames extends ListRecords
{
    protected static string $resource = EventNameResource::class;

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('register')
                ->label('Register an event name')
                ->icon('heroicon-o-plus')
                ->modalHeading('Add a name an endpoint may subscribe to')
                ->modalDescription('The catalogue is yours: registering a name here does not make anything raise it, and this module never learns what one means. It only refuses a subscription to a name nobody registered.')
                ->modalSubmitActionLabel('Register it')
                ->schema([
                    TextInput::make('name')->label('Event name')->required()->maxLength(128),
                    TextInput::make('description')
                        ->label('What it means to you')
                        ->maxLength(191)
                        ->helperText('For whoever configures the subscription. Nothing in the module reads it.'),
                ])
                ->action(function (array $data): void {
                    /** @var array{name: string, description: ?string} $data */
                    try {
                        $name = App::make(RegisterEventName::class)(
                            PanelTenant::current(),
                            $data['name'],
                            $data['description'],
                        );
                    } catch (EventNameIsAlreadyRegistered $already) {
                        Say::it('Nothing was registered', $already->getMessage(), 'danger');

                        return;
                    }

                    Say::it(
                        'Registered',
                        $name->name.' can now be subscribed to. Nothing is sent for it until an endpoint holds a subscription and somebody raises it.',
                        'success',
                    );
                }),
        ];
    }
}
