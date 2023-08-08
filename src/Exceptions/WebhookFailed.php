<?php

declare(strict_types=1);

namespace WayOfDev\StripeWebhooks\Exceptions;

use Exception;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Response;
use WayOfDev\WebhookClient\Entities\WebhookCall;

final class WebhookFailed extends Exception
{
    public static function jobClassDoesNotExist(string $jobClass, WebhookCall $webhookCall): self
    {
        return new self("Could not process webhook id `{$webhookCall->id()}` of type `{$webhookCall->name()} because the configured jobclass `$jobClass` does not exist.");
    }

    public static function missingType(WebhookCall $webhookCall): self
    {
        return new self("Webhook call id `{$webhookCall->id()}` did not contain a type. Valid Stripe webhook calls should always contain a type.");
    }

    public function render($request): Response|ResponseFactory
    {
        return response(['error' => $this->getMessage()], 400);
    }
}
