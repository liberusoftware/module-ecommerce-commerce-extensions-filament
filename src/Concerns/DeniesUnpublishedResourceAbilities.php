<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CommerceExtensions\Filament\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Every ability the domain does not publish, forced closed by name. A missing
 * policy method is permissive — Filament falls through to `allow()` — so an
 * ability nobody thought about is open unless it is answered.
 *
 * `canCreate` and `canEdit` are closed because there is no create or edit form
 * anywhere here: an extension, an endpoint, an event name and a subscription are
 * each made by a published action that takes the tenant as its first argument,
 * and a Filament form would write the row without one.
 *
 * `canDelete` is closed because nothing in this domain is deleted. Retiring is a
 * dated fact and the delivery log outlives the endpoint; the host's cascade,
 * where deleting a misconfigured URL destroyed its security log, is the fault
 * that rule exists against. The one deletion the domain does publish is
 * retention, which is a scheduled command and not a button.
 *
 * `canViewAny` and `canView` are not in this list. They are stated on each
 * resource and answered there.
 */
trait DeniesUnpublishedResourceAbilities
{
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canForceDelete(Model $record): bool
    {
        return false;
    }

    public static function canForceDeleteAny(): bool
    {
        return false;
    }

    public static function canReorder(): bool
    {
        return false;
    }

    public static function canReplicate(Model $record): bool
    {
        return false;
    }

    public static function canRestore(Model $record): bool
    {
        return false;
    }

    public static function canRestoreAny(): bool
    {
        return false;
    }
}
