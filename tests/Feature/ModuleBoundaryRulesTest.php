<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Liberu\Ecommerce\CommerceExtensions\Filament\CommerceExtensionsPlugin;
use Liberu\Ecommerce\CommerceExtensions\Filament\Resources\Deliveries\DeliveryResource;
use Liberu\Ecommerce\CommerceExtensions\Filament\Resources\Endpoints\EndpointResource;
use Liberu\Ecommerce\CommerceExtensions\Filament\Resources\EventNames\EventNameResource;
use Liberu\Ecommerce\CommerceExtensions\Filament\Resources\Extensions\ExtensionResource;
use Liberu\Ecommerce\CommerceExtensions\Filament\Widgets\DeliveryStanding;
use Liberu\Ecommerce\CommerceExtensions\Filament\Widgets\DueNow;

/*
 * The rules that are this package's rather than the fleet's. The shared boundary
 * suite in `package-testbench` asserts the manifest, the required files, the
 * absence of `App\` and the panel plugins.
 */

function packageRoot(): string
{
    return dirname(__DIR__, 2);
}

/** @return array<int, string> every PHP file under src/, absolute. */
function sourceFiles(): array
{
    $files = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(packageRoot().'/src')) as $file) {
        if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }

    sort($files);

    return $files;
}

/**
 * Source with comments stripped. Every rule below is about what this package
 * does, and a docblock naming the host fault it refuses to repeat is the text
 * most likely to trip a naive grep.
 */
function sourceCode(string $path): string
{
    $code = '';

    foreach (token_get_all((string) file_get_contents($path)) as $token) {
        if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }

        $code .= is_array($token) ? $token[1] : $token;
    }

    return $code;
}

/** @return array<string, mixed> */
function packageJson(string $file): array
{
    /** @var array<string, mixed> $decoded */
    $decoded = json_decode((string) file_get_contents(packageRoot().'/'.$file), true, 512, JSON_THROW_ON_ERROR);

    return $decoded;
}

it('reads a secret back nowhere, because nothing in the domain gives one back', function (): void {
    // Both columns are encrypted and hidden, and the only object that ever
    // carries one is `IssuedSecret`, at issue. A screen that read
    // `$endpoint->secret` would be reaching for something the module says it
    // does not publish, and one that displayed it would keep a credential on a
    // page long after the moment it was minted.
    foreach (sourceFiles() as $file) {
        $source = sourceCode($file);

        expect($source)->not->toMatch('/->previous_secret\b/');

        if (basename($file) !== 'Render.php') {
            expect($source)->not->toMatch('/->secret\b/');
        }
    }

    // And the one place that does is the sentence shown at issue.
    expect(sourceCode(packageRoot().'/src/Support/Render.php'))->toContain('$issued->secret');
});

it('registers everything through the plugin and nothing from the service provider', function (): void {
    $provider = (string) file_get_contents(packageRoot().'/src/CommerceExtensionsFilamentServiceProvider.php');

    expect($provider)->not->toContain('Resource::class')
        ->and($provider)->not->toContain('->resources(')
        ->and($provider)->not->toContain('->pages(')
        ->and($provider)->not->toContain('->widgets(');

    $plugin = (string) file_get_contents(packageRoot().'/src/CommerceExtensionsPlugin.php');

    foreach (['ExtensionResource::class', 'EndpointResource::class', 'EventNameResource::class',
        'DeliveryResource::class', 'DeliveryStanding::class', 'DueNow::class'] as $registered) {
        expect($plugin)->toContain($registered);
    }
});

it('declares every class the manifest names for the panel', function (): void {
    $manifest = packageJson('module.json');

    expect($manifest['presentation']['filament']['app'])->toBe([CommerceExtensionsPlugin::class]);

    foreach ($manifest['presentation']['filament'] as $plugins) {
        foreach ($plugins as $plugin) {
            expect(class_exists($plugin))->toBeTrue();
        }
    }

    foreach ([ExtensionResource::class, EndpointResource::class, EventNameResource::class,
        DeliveryResource::class, DeliveryStanding::class, DueNow::class] as $class) {
        expect(class_exists($class))->toBeTrue();
    }
});

it('answers canView on every widget, which Filament leaves open by default', function (): void {
    // A widget is the surface the panel's own tenancy does not reach.
    foreach ([packageRoot().'/src/Widgets/DeliveryStanding.php', packageRoot().'/src/Widgets/DueNow.php'] as $widget) {
        expect(sourceCode($widget))
            ->toContain('public static function canView(): bool')
            ->toContain('PanelTenant::resolvable()')
            // And every read it makes says which merchant it is for.
            ->toContain('PanelTenant::current()');
    }
});

it('guards no action with visible, so the domain’s own refusal stays reachable', function (): void {
    // Filament re-evaluates `visible()` against a freshly hydrated record at
    // mount and again at `callMountedAction`, so a guard restating "this is
    // already settled" or "there is no previous secret" would make the domain's
    // branch unreachable — and an unreachable branch is unreachable against a
    // 100% bar. The guards are dropped and the branches stay.
    foreach (sourceFiles() as $file) {
        expect(sourceCode($file))->not->toContain('->visible(')
            ->and(sourceCode($file))->not->toContain('->hidden(');
    }
});

it('ships no Filament create, edit or delete control of any kind', function (): void {
    // Every write here is a published domain action taking the tenant as its
    // first argument. A Filament form would write the row without one, and
    // nothing in this domain is deleted at all: retiring is a dated fact and the
    // delivery log outlives the endpoint.
    $forbidden = [
        'CreateAction', 'EditAction', 'DeleteAction', 'DeleteBulkAction', 'ForceDeleteAction',
        'RestoreAction', 'ReplicateAction', 'AssociateAction', 'DissociateAction',
        'AttachAction', 'DetachAction', 'CreateRecord', 'EditRecord', 'ManageRecords',
    ];

    foreach (sourceFiles() as $file) {
        $source = sourceCode($file);

        foreach ($forbidden as $control) {
            expect($source)->not->toContain($control);
        }
    }
});

it('writes nothing through Eloquent, because every write here is a published domain action', function (): void {
    foreach (sourceFiles() as $file) {
        $source = sourceCode($file);

        expect($source)->not->toContain('->save()')
            ->and($source)->not->toContain('->update(')
            ->and($source)->not->toContain('->delete()')
            ->and($source)->not->toContain('->forceFill(')
            ->and($source)->not->toContain('->increment(')
            ->and($source)->not->toContain('->decrement(')
            ->and($source)->not->toContain('::create(');
    }
});

it('never states an outcome, a reason or a table name of its own', function (): void {
    // A `where('outcome', 'delivered')` here would be this panel deciding what
    // a delivered delivery is. The enums decide, and every comparison in this
    // package is against a case or a `->value` rather than a string it chose.
    foreach (sourceFiles() as $file) {
        $source = sourceCode($file);

        foreach ([
            "'delivered'", "'abandoned'", "'redacted'", "'pending'", "'failed'", "'refused'",
            "'no_transport_bound'", "'window_closed'", "'concurrent_attempt'",
            'commerce_extensions_',
        ] as $forbidden) {
            expect($source)->not->toContain($forbidden);
        }

        expect($source)->not->toMatch("/->where\\('(outcome|refusal_reason|event_name)', '/");
    }
});

it('takes every option on every select and filter from the domain', function (): void {
    foreach ([
        'src/Resources/Deliveries/DeliveryResource.php',
        'src/Widgets/DeliveryStanding.php',
    ] as $source) {
        expect(sourceCode(packageRoot().'/'.$source))->toContain('::cases()');
    }

    foreach ([
        'src/Resources/Endpoints/Pages/ListEndpoints.php' => 'ListExtensions()',
        'src/Resources/Endpoints/Pages/ViewEndpoint.php' => 'ListEventNames()',
        'src/Resources/Deliveries/DeliveryResource.php' => 'ListEndpoints()',
    ] as $source => $query) {
        expect(sourceCode(packageRoot().'/'.$source))->toContain($query);
    }
});

it('never puts a subject reference or a payload on a listing', function (): void {
    // Wave 11 shipped reviewer PII on a public listing. The subject reference
    // and the stored bytes are on the record and neither is on the index.
    foreach (sourceFiles() as $file) {
        expect(sourceCode($file))->not->toContain("TextColumn::make('subject_ref')")
            ->and(sourceCode($file))->not->toContain("TextColumn::make('payload')")
            ->and(sourceCode($file))->not->toContain("TextColumn::make('cause_ref')");
    }

    $resource = sourceCode(packageRoot().'/src/Resources/Deliveries/DeliveryResource.php');

    expect($resource)->toContain("TextEntry::make('subject_ref')")
        ->and($resource)->toContain("TextEntry::make('payload')");
});

it('offers neither of the two controls that span every merchant', function (): void {
    // Erasure and export both say in their names that they cross tenants. A
    // screen scoped to one merchant must not carry a control that acts on all.
    foreach (sourceFiles() as $file) {
        expect(sourceCode($file))->not->toContain('AcrossTenants');
    }
});

it('offers no control that raises an event, because a panel has no payload to hand in', function (): void {
    // The module is handed a name, a cause, a subject and already-serialised
    // bytes. A button that made those up would be this package building a
    // payload, which is the single thing the module refuses to do.
    foreach (sourceFiles() as $file) {
        expect(sourceCode($file))->not->toContain('Actions\\RaiseEvent')
            ->and(sourceCode($file))->not->toContain('Actions\\PruneDeliveries');
    }
});

it('reaches for no framework-foundation helper', function (): void {
    // `config()`, `app()`, `auth()`, `now()` and `view()` live in
    // `laravel/framework`, not in `illuminate/support`. They pass CI only
    // because the testbench drags the framework in.
    foreach (sourceFiles() as $file) {
        $source = sourceCode($file);

        // The lookbehind excludes `:` so `CarbonImmutable::now()` — which is
        // `nesbot/carbon` and reachable — is not mistaken for the helper.
        expect($source)->not->toMatch('/(?<![\w>$:])config\(/')
            ->and($source)->not->toMatch('/(?<![\w>$:])app\(/')
            ->and($source)->not->toMatch('/(?<![\w>$:])auth\(/')
            ->and($source)->not->toMatch('/(?<![\w>$:])now\(/')
            ->and($source)->not->toMatch('/(?<![\w>$:])view\(/')
            ->and($source)->not->toMatch('/(?<![\w>$:])session\(/')
            ->and($source)->not->toMatch('/(?<![\w>$:])response\(/');
    }
});

it('reads one clock and writes every tenant where in one file', function (): void {
    // The domain reads no clock: every action takes the instant it is reasoning
    // about. A page resolving `now()` per widget shows parts that disagree
    // across a minute boundary. And the domain publishes no query over attempts
    // at all, so the two `where`s that read one are in the same file as the
    // clock, which is the whole of the gap `docs/panel.md` records.
    $clocks = [];
    $wheres = [];
    $models = [];

    foreach (sourceFiles() as $file) {
        $source = sourceCode($file);
        $name = basename($file);

        if (str_contains($source, 'CarbonImmutable::now()') || str_contains($source, 'Carbon::now()')) {
            $clocks[] = $name;
        }

        if (str_contains($source, '->where(')) {
            $wheres[$name] = substr_count($source, '->where(');
        }

        if (str_contains($source, '::query()')) {
            $models[$name] = substr_count($source, '::query()');
        }
    }

    expect($clocks)->toBe(['Snapshot.php'])
        ->and($wheres)->toBe(['Snapshot.php' => 4])
        ->and($models)->toBe(['Snapshot.php' => 2]);
});

it('states its Filament floor as the true one', function (): void {
    // Every tag from v5.4.0 declares `illuminate/contracts: ^11.28|^12.0|^13.0`.
    expect(packageJson('composer.json')['require']['filament/filament'])->toBe('^5.4');
});

it('lists the sibling packages the manifest declares and no others', function (): void {
    $composer = packageJson('composer.json');
    $manifest = packageJson('module.json');

    $siblings = array_filter(
        $composer['require'],
        fn (string $constraint, string $package): bool => str_starts_with($package, 'liberusoftware/'),
        ARRAY_FILTER_USE_BOTH,
    );

    expect($siblings)->toBe($manifest['requires']['packages'])
        ->and($siblings)->toBe(['liberusoftware/ecommerce-commerce-extensions' => '^0.1']);
});

it('points at the domain repository, because it is not on Packagist yet', function (): void {
    expect(packageJson('composer.json')['repositories'])->toBe([[
        'type' => 'vcs',
        'url' => 'https://github.com/liberusoftware/module-ecommerce-commerce-extensions',
    ]]);
});

it('agrees with itself about its own version', function (): void {
    expect(packageJson('composer.json')['version'])->toBe(packageJson('module.json')['version']);
});

it('carries no session identifier in any file', function (): void {
    $files = array_merge(sourceFiles(), [
        packageRoot().'/README.md',
        packageRoot().'/CHANGELOG.md',
        packageRoot().'/module.json',
        packageRoot().'/docs/panel.md',
        packageRoot().'/docs/adoption.md',
        packageRoot().'/docs/runbook.md',
    ]);

    foreach ($files as $file) {
        $source = (string) file_get_contents($file);

        // Split so this assertion is not itself the thing it forbids: a
        // repository-wide grep for the literals has to come back empty.
        expect($source)->not->toContain('claude'.'.ai')
            ->and($source)->not->toContain('Claude-'.'Session');
    }
});

it('leaves the panel’s own tenancy switched off, because the domain tenant is derived once', function (): void {
    // Filament tenancy resolves the host's `Team`; the merchant this module
    // scopes on is derived from it in `PanelTenant` and nowhere else.
    foreach ([
        'src/Resources/Extensions/ExtensionResource.php',
        'src/Resources/Endpoints/EndpointResource.php',
        'src/Resources/EventNames/EventNameResource.php',
        'src/Resources/Deliveries/DeliveryResource.php',
    ] as $resource) {
        expect(sourceCode(packageRoot().'/'.$resource))->toContain('$isScopedToTenant = false');
    }
});

it('contributes nothing when a panel boots it, so nothing can arrive on a panel that did not register it', function (): void {
    $plugin = CommerceExtensionsPlugin::make();
    $plugin->boot(Filament::getPanel('app'));

    expect($plugin->getId())->toBe('ecommerce-commerce-extensions');
});
