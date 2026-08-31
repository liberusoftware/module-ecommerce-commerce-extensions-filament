<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CommerceExtensions\Filament\Tests;

/**
 * The merchant the test panel is showing. Mutable, because every custody proof
 * here gives the second merchant deliberately identical values — two merchants
 * both registering a partner called `partner-1`, both receiving at the same
 * URL and both raising a cause reference their own storefront minted is the
 * ordinary case, and a proof that creates one merchant's rows proves nothing
 * about a `where` nobody wrote.
 */
final class TestTenant
{
    public const PRIMARY = 'tenant-a';

    public const OTHER = 'tenant-b';

    private static string $current = self::PRIMARY;

    public static function current(): string
    {
        return self::$current;
    }

    public static function use(string $tenantId): void
    {
        self::$current = $tenantId;
    }

    public static function reset(): void
    {
        self::$current = self::PRIMARY;
    }
}
