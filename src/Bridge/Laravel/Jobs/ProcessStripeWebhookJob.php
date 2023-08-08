<?php

declare(strict_types=1);

namespace WayOfDev\StripeWebhooks\Bridge\Laravel\Jobs;

use WayOfDev\StripeWebhooks\Exceptions\WebhookFailed;
use WayOfDev\WebhookClient\Bridge\Laravel\Jobs\ProcessWebhookJob;
use WayOfDev\WebhookClient\Entities\WebhookCall;

use function class_exists;
use function str_replace;

class ProcessStripeWebhookJob extends ProcessWebhookJob
{
    public function __construct(WebhookCall $webhookCall)
    {
        parent::__construct($webhookCall);
        $this->onConnection(config('stripe-webhooks.connection'));
        $this->onQueue(config('stripe-webhooks.queue'));
    }

    /**
     * @throws WebhookFailed
     */
    public function handle(): void
    {
        if (! isset($this->webhookCall->payload()->toArray()['type']) || $this->webhookCall->payload()->toArray()['type'] === '') {
            throw WebhookFailed::missingType($this->webhookCall);
        }

        event("stripe-webhooks::{$this->webhookCall->payload()->toArray()['type']}", $this->webhookCall);

        $jobClass = $this->determineJobClass($this->webhookCall->payload()->toArray()['type']);

        if ('' === $jobClass) {
            return;
        }

        if (! class_exists($jobClass)) {
            throw WebhookFailed::jobClassDoesNotExist($jobClass, $this->webhookCall);
        }

        dispatch(new $jobClass($this->webhookCall));
    }

    protected function determineJobClass(string $eventType): string
    {
        $jobConfigKey = str_replace('.', '_', $eventType);
        $defaultJob = config('stripe-webhooks.default_job', '');

        return config("stripe-webhooks.jobs.{$jobConfigKey}", $defaultJob);
    }
}
