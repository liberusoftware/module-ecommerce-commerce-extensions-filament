<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CommerceExtensions\Filament\Resources\Extensions\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\App;
use Liberu\Ecommerce\CommerceExtensions\Actions\RegisterExtension;
use Liberu\Ecommerce\CommerceExtensions\Exceptions\ExtensionIsAlreadyRegistered;
use Liberu\Ecommerce\CommerceExtensions\Filament\Resources\Extensions\ExtensionResource;
use Liberu\Ecommerce\CommerceExtensions\Filament\Support\PanelTenant;
use Liberu\Ecommerce\CommerceExtensions\Filament\Support\Say;

final class ListExtensions extends ListRecords
{
    protected static string $resource = ExtensionResource::class;

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('register')
                ->label('Register an extension')
                ->icon('heroicon-o-plus')
                ->modalHeading('Register a third party that receives events')
                ->modalDescription('A record, not a package. Nothing is installed and no code boots: this names a third party so its endpoints can be given somewhere to receive.')
                ->modalSubmitActionLabel('Register it')
                ->schema([
                    TextInput::make('extension_ref')
                        ->label('Reference')
                        ->required()
                        ->maxLength(128)
                        ->helperText('Your own reference for the third party. It is unique to you: another merchant using the same string is a different extension.'),
                    TextInput::make('name')->label('Name')->required()->maxLength(191),
                ])
                ->action(function (array $data): void {
                    /** @var array{extension_ref: string, name: string} $data */
                    try {
                        $extension = App::make(RegisterExtension::class)(
                            PanelTenant::current(),
                            $data['extension_ref'],
                            $data['name'],
                        );
                    } catch (ExtensionIsAlreadyRegistered $already) {
                        Say::it('Nothing was registered', $already->getMessage(), 'danger');

                        return;
                    }

                    Say::it(
                        'Registered',
                        $extension->name.' is registered under the reference '.$extension->extension_ref
                            .'. Give it an endpoint to receive at, and subscribe that endpoint to the event names you have registered.',
                        'success',
                    );
                }),
        ];
    }
}
