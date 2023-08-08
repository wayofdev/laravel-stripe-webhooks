<?php

declare(strict_types=1);

namespace WayOfDev\StripeWebhooks\SignatureValidator;

use Exception;
use Illuminate\Http\Request;
use Stripe\Webhook;
use WayOfDev\WebhookClient\Config;
use WayOfDev\WebhookClient\Contracts\SignatureValidator;

class StripeSignatureValidator implements SignatureValidator
{
    public function isValid(Request $request, Config $config): bool
    {
        if (config('stripe-webhooks.verify_signature') === false) {
            return true;
        }

        $signature = $request->header('Stripe-Signature');
        $secret = $config->signingSecret;

        try {
            Webhook::constructEvent($request->getContent(), $signature, $secret);
        } catch (Exception) {
            return false;
        }

        return true;
    }
}
