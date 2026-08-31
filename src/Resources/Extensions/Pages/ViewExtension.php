<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CommerceExtensions\Filament\Resources\Extensions\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\App;
use Liberu\Ecommerce\CommerceExtensions\Actions\ReinstateExtension;
use Liberu\Ecommerce\CommerceExtensions\Actions\RetireExtension;
use Liberu\Ecommerce\CommerceExtensions\Filament\Resources\Extensions\ExtensionResource;
use Liberu\Ecommerce\CommerceExtensions\Filament\Support\PanelTenant;
use Liberu\Ecommerce\CommerceExtensions\Filament\Support\Say;
use Liberu\Ecommerce\CommerceExtensions\Filament\Support\Snapshot;
use Liberu\Ecommerce\CommerceExtensions\Models\Extension;

/**
 * Neither action carries a `visible()` guard. Filament re-evaluates one against
 * a freshly hydrated record at mount and again at `callMountedAction`, so a
 * guard restating "this is already retired" would make the domain's own answer
 * unreachable — and the domain's answer here is that retiring twice keeps the
 * first date, which is a sentence worth reading rather than a button worth
 * hiding.
 */
final class ViewExtension extends ViewRecord
{
    protected static string $resource = ExtensionResource::class;

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('retire')
                ->label('Retire it')
                ->icon('heroicon-o-hand-raised')
                ->color('warning')
                ->modalHeading('Retire this extension')
                ->modalDescription('A dated fact, not a delete. Every delivery it received survives, every endpoint it holds stops receiving, and an attempt on one of them refuses with a reason rather than failing.')
                ->modalSubmitActionLabel('Retire it')
                ->action(function (Extension $record): void {
                    $retired = App::make(RetireExtension::class)(
                        PanelTenant::current(),
                        $record->id,
                        Snapshot::asOf(),
                    );

                    Say::it(
                        'Retired',
                        $retired->name.' has been retired as of '.((string) $retired->retired_at?->toDateTimeString())
                            .'. Retiring it again keeps that date rather than moving it.',
                        'success',
                    );
                }),

            Action::make('reinstate')
                ->label('Reinstate it')
                ->icon('heroicon-o-arrow-path')
                ->modalHeading('Reinstate this extension')
                ->modalDescription('Without this a mis-clicked retirement would be permanent: the reference is a natural key, so registering the same third party again is refused.')
                ->modalSubmitActionLabel('Reinstate it')
                ->action(function (Extension $record): void {
                    $reinstated = App::make(ReinstateExtension::class)(PanelTenant::current(), $record->id);

                    Say::it(
                        'Reinstated',
                        $reinstated->name.' is live again. Its endpoints receive whatever they were subscribed to before, unless they were retired in their own right.',
                        'success',
                    );
                }),
        ];
    }
}
