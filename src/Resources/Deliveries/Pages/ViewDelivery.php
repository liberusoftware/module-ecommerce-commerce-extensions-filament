<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CommerceExtensions\Filament\Resources\Deliveries\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\App;
use Liberu\Ecommerce\CommerceExtensions\Actions\AttemptDelivery;
use Liberu\Ecommerce\CommerceExtensions\Data\AttemptReport;
use Liberu\Ecommerce\CommerceExtensions\Enums\AttemptOutcome;
use Liberu\Ecommerce\CommerceExtensions\Exceptions\DeliveryIsAlreadySettled;
use Liberu\Ecommerce\CommerceExtensions\Filament\Resources\Deliveries\DeliveryResource;
use Liberu\Ecommerce\CommerceExtensions\Filament\Support\PanelTenant;
use Liberu\Ecommerce\CommerceExtensions\Filament\Support\Render;
use Liberu\Ecommerce\CommerceExtensions\Filament\Support\Say;
use Liberu\Ecommerce\CommerceExtensions\Filament\Support\Snapshot;
use Liberu\Ecommerce\CommerceExtensions\Models\Delivery;

/**
 * One delivery, and the one thing a merchant can do about it.
 *
 * The action carries no `visible()` guard. A settled delivery raises
 * `DeliveryIsAlreadySettled` and there is no re-open verb, deliberately — and
 * Filament re-evaluates `visible()` against a freshly hydrated record at mount
 * and again at `callMountedAction`, so hiding the button on a settled row would
 * make the domain's own refusal unreachable rather than merely tidy. The guard
 * is dropped; the refusal is caught and read out.
 */
final class ViewDelivery extends ViewRecord
{
    protected static string $resource = DeliveryResource::class;

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('attempt')
                ->label('Attempt it now')
                ->icon('heroicon-o-paper-airplane')
                ->modalHeading('Make one attempt at this delivery')
                ->modalDescription('Every way this can fail to send is a row with a reason on it: nothing bound to carry the request, the endpoint or its extension retired, the destination no longer resolving into public space, the window closed, or another worker already holding this attempt number. None of them is a delivery that quietly did not go.')
                ->modalSubmitActionLabel('Attempt it')
                ->action(function (Delivery $record): void {
                    try {
                        $report = App::make(AttemptDelivery::class)(
                            PanelTenant::current(),
                            $record->id,
                            Snapshot::asOf(),
                        );
                    } catch (BindingResolutionException) {
                        // `Seams::transport()` resolves a configured class name
                        // outside any try, and `AttemptDelivery` does not catch
                        // it, so a host typo raises here rather than arriving as
                        // a refusal row. Caught so the panel says which of the
                        // three states the seam is in instead of showing an
                        // error page; the gap is recorded in docs/panel.md.
                        Say::it(
                            'Nothing was attempted, and no attempt was recorded',
                            Render::transport(null).'. Unlike every other way this can fail to send, it leaves no row: the domain resolves the binding before it writes one.',
                            'danger',
                        );

                        return;
                    } catch (DeliveryIsAlreadySettled $settled) {
                        Snapshot::forget();

                        Say::it(
                            'Nothing was attempted',
                            $settled->getMessage().' A settled delivery is not re-opened: there is no verb for it, because an event that was delivered, abandoned or redacted is a fact about a moment that has passed.',
                            'danger',
                        );

                        return;
                    }

                    Snapshot::forget();

                    Say::it(self::heading($report), Render::attempted($report), self::colour($report));
                }),
        ];
    }

    /**
     * A refusal is neither a success nor a failure, and reads as neither.
     *
     * Branched rather than matched over the outcome enum, because `Pending` is
     * a state an attempt row can be found in and never one this report carries:
     * the row is written pending and the report is built after it settles.
     */
    private static function heading(AttemptReport $report): string
    {
        if ($report->refusalReason !== null) {
            return 'Nothing went out, and here is why';
        }

        return $report->outcome === AttemptOutcome::Delivered
            ? 'It went, and the receiver accepted it'
            : 'It went, and the receiver did not accept it';
    }

    private static function colour(AttemptReport $report): string
    {
        if ($report->refusalReason !== null) {
            return 'warning';
        }

        return $report->outcome === AttemptOutcome::Delivered ? 'success' : 'danger';
    }
}
