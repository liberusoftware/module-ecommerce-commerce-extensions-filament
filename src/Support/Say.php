<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CommerceExtensions\Filament\Support;

use Filament\Notifications\Notification;

/**
 * What the panel says after a domain action answered.
 *
 * Persistent, always. One of these carries a signing secret, which exists
 * outside the module for exactly as long as it is on the screen: a notification
 * that faded on a timer would destroy it as surely as a redirect.
 */
final class Say
{
    public static function it(string $title, string $body, string $colour): void
    {
        Notification::make()
            ->title($title)
            ->body($body)
            ->color($colour)
            ->persistent()
            ->send();
    }
}
