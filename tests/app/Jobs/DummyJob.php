<?php

declare(strict_types=1);

namespace WayOfDev\StripeWebhooks\App\Jobs;

use WayOfDev\WebhookClient\Entities\WebhookCall;

class DummyJob
{
    public function __construct(
        public WebhookCall $webhookCall
    ) {
    }

    public function handle(): void
    {
        cache()->put('dummyJob', $this->webhookCall);
    }
}
