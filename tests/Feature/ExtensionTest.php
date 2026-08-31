<?php

declare(strict_types=1);

use Liberu\Ecommerce\CommerceExtensions\Filament\Resources\Extensions\ExtensionResource;
use Liberu\Ecommerce\CommerceExtensions\Filament\Resources\Extensions\Pages\ListExtensions;
use Liberu\Ecommerce\CommerceExtensions\Filament\Resources\Extensions\Pages\ViewExtension;
use Liberu\Ecommerce\CommerceExtensions\Filament\Tests\TestTenant;
use Liberu\Ecommerce\CommerceExtensions\Models\Extension;
use Livewire\Livewire;

it('registers a third party as a record, and nothing boots', function (): void {
    Livewire::test(ListExtensions::class)
        ->callAction('register', ['extension_ref' => 'partner-1', 'name' => 'A Partner']);

    $notification = lastNotification();

    expect($notification['title'])->toBe('Registered')
        ->and($notification['color'])->toBe('success')
        ->and(Extension::query()->where('tenant_id', TestTenant::PRIMARY)->count())->toBe(1);
});

it('refuses a second registration of the same reference rather than answering with the first', function (): void {
    anExtension();

    Livewire::test(ListExtensions::class)
        ->callAction('register', ['extension_ref' => 'partner-1', 'name' => 'Something Else']);

    $notification = lastNotification();

    expect($notification['title'])->toBe('Nothing was registered')
        ->and($notification['color'])->toBe('danger')
        ->and($notification['body'])->toContain('partner-1')
        ->and(Extension::query()->where('tenant_id', TestTenant::PRIMARY)->count())->toBe(1);
});

it('retires as a dated fact and keeps the first date when it is retired again', function (): void {
    // No `visible()` guard, so the domain's own answer is reachable: retiring
    // twice is the same fact and keeps the date it already had.
    $extension = anExtension();

    Livewire::test(ViewExtension::class, ['record' => $extension->getKey()])->callAction('retire');

    $first = $extension->fresh()?->retired_at;

    expect(lastNotification()['title'])->toBe('Retired')
        ->and($first)->not->toBeNull();

    Livewire::test(ViewExtension::class, ['record' => $extension->getKey()])->callAction('retire');

    expect($extension->fresh()?->retired_at?->toDateTimeString())->toBe($first?->toDateTimeString());
});

it('reinstates, because the reference is a natural key and a mis-click would otherwise be permanent', function (): void {
    $extension = anExtension();

    Livewire::test(ViewExtension::class, ['record' => $extension->getKey()])->callAction('retire');
    Livewire::test(ViewExtension::class, ['record' => $extension->getKey()])->callAction('reinstate');

    expect(lastNotification()['title'])->toBe('Reinstated')
        ->and($extension->fresh()?->retired_at)->toBeNull()
        ->and(ExtensionResource::standing($extension->fresh() ?? $extension))->toBe('Live');
});

it('lists this merchant’s extensions and counts their endpoints through the restated relation', function (): void {
    $extension = anExtension();
    anEndpoint(url: 'https://one.invalid/hook');
    anEndpoint(url: 'https://two.invalid/hook');

    Livewire::test(ListExtensions::class)
        ->assertCanSeeTableRecords(Extension::query()->whereKey($extension->getKey())->get());

    expect(ExtensionResource::getEloquentQuery()->firstOrFail()->endpoints_count)->toBe(2);
});
