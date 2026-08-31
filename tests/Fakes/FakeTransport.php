<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CommerceExtensions\Filament\Tests\Fakes;

use Liberu\Ecommerce\CommerceExtensions\Contracts\DeliveryTransport;
use Liberu\Ecommerce\CommerceExtensions\Data\SignedRequest;
use Liberu\Ecommerce\CommerceExtensions\Data\TransportResponse;
use Throwable;

final class FakeTransport implements DeliveryTransport
{
    /** @var list<SignedRequest> */
    public array $sent = [];

    public function __construct(
        private readonly int $status = 200,
        private readonly ?string $body = 'thanks',
        private readonly int $durationMs = 12,
        private readonly ?Throwable $failure = null,
    ) {}

    public function send(SignedRequest $request): TransportResponse
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }

        $this->sent[] = $request;

        return new TransportResponse($this->status, $this->body, $this->durationMs);
    }
}
