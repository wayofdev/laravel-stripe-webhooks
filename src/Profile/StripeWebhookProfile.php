<?php

declare(strict_types=1);

namespace WayOfDev\StripeWebhooks\Profile;

use Cycle\Database\Injection\Parameter;
use Cycle\ORM\ORMInterface;
use Illuminate\Http\Request;
use WayOfDev\WebhookClient\Contracts\WebhookProfile;
use WayOfDev\WebhookClient\Entities\WebhookCall;
use WayOfDev\WebhookClient\Persistence\ORMWebhookCallRepository;

class StripeWebhookProfile implements WebhookProfile
{
    public function __construct(private readonly ORMInterface $orm)
    {
    }

    public function shouldProcess(Request $request): bool
    {
        /** @var ORMWebhookCallRepository $webhookCallsRepository */
        $webhookCallsRepository = $this->orm->getRepository(WebhookCall::class);

        $exists = $webhookCallsRepository
            ->select()
            ->where(['name' => 'stripe'])
            ->andWhere("JSON_EXTRACT(payload, '$.id')", '=', new Parameter(['payloadId' => $request->get('id')]))
            ->count();

        return 0 === $exists;
    }
}
