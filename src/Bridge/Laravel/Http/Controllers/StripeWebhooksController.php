<?php

declare(strict_types=1);

namespace WayOfDev\StripeWebhooks\Bridge\Laravel\Http\Controllers;

use Cycle\ORM\ORMInterface;
use Illuminate\Http\Request;
use WayOfDev\StripeWebhooks\Bridge\Laravel\Jobs\ProcessStripeWebhookJob;
use WayOfDev\StripeWebhooks\SignatureValidator\StripeSignatureValidator;
use WayOfDev\WebhookClient\Config;
use WayOfDev\WebhookClient\Contracts\WebhookCallRepository;
use WayOfDev\WebhookClient\Exceptions\InvalidConfig;
use WayOfDev\WebhookClient\Exceptions\InvalidWebhookSignature;
use WayOfDev\WebhookClient\WebhookProcessor;

class StripeWebhooksController
{
    /**
     * @throws InvalidWebhookSignature
     * @throws InvalidConfig
     */
    public function __invoke(Request $request, ORMInterface $orm, ?string $configKey = null)
    {
        /** @var WebhookCallRepository $repository */
        $repository = $orm->getRepository(config('stripe-webhooks.entity'));

        $webhookConfig = new Config([
            'name' => 'stripe',
            'signing_secret' => (null !== $configKey) ?
                config('stripe-webhooks.signing_secret_' . $configKey) :
                config('stripe-webhooks.signing_secret'),
            'signature_header_name' => 'Stripe-Signature',
            'signature_validator' => StripeSignatureValidator::class,
            'webhook_profile' => config('stripe-webhooks.profile'),
            'webhook_entity' => config('stripe-webhooks.entity'),
            'webhook_entity_repository' => config('stripe-webhooks.entity_repository'),
            'process_webhook_job' => ProcessStripeWebhookJob::class,
        ]);

        return (new WebhookProcessor($request, $webhookConfig, $repository))->process();
    }
}
