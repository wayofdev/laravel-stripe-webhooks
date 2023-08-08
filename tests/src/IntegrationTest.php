<?php

declare(strict_types=1);

namespace WayOfDev\StripeWebhooks\Tests;

use Cycle\Database\Injection\Parameter;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use JsonException;
use WayOfDev\StripeWebhooks\App\Jobs\DummyJob;
use WayOfDev\WebhookClient\Entities\WebhookCall;

final class IntegrationTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        Event::fake();

        Route::stripeWebhooks('stripe-webhooks');
        Route::stripeWebhooks('stripe-webhooks/{configKey}');

        config(['stripe-webhooks.jobs' => ['my_type' => DummyJob::class]]);
        cache()->clear();
    }

    /**
     * @test
     *
     * @throws JsonException
     */
    public function it_can_handle_a_valid_request(): void
    {
        $this->withoutExceptionHandling();

        $payload = [
            'type' => 'my.type',
            'key' => 'value',
        ];

        $headers = ['Stripe-Signature' => $this->determineStripeSignature($payload)];

        $this
            ->postJson('stripe-webhooks', $payload, $headers)
            ->assertSuccessful();

        $this::assertCount(1, $this->repository->findAll());

        $webhookCall = $this->repository->first();

        $this::assertEquals('my.type', $webhookCall->payload()->toArray()['type']);
        $this::assertEquals($payload, $webhookCall->payload()->toArray());
        $this::assertEmpty($webhookCall->exception()->toArray());

        Event::assertDispatched('stripe-webhooks::my.type', function ($event, $eventPayload) use ($webhookCall) {
            $this::assertInstanceOf(WebhookCall::class, $eventPayload);
            $this::assertEquals($webhookCall->id, $eventPayload->id);

            return true;
        });

        $this::assertEquals($webhookCall->id, cache('dummyJob')->id);
    }

    /**
     * @test
     */
    public function a_request_with_invalid_signature_with_verification_disabled_will_pass(): void
    {
        config(['stripe-webhooks.verify_signature' => false]);
        cache()->clear();

        $this->withoutExceptionHandling();

        $payload = [
            'type' => 'my.type',
            'key' => 'value',
        ];

        $headers = ['Stripe-Signature' => 'invalid signature'];

        $this
            ->postJson('stripe-webhooks', $payload, $headers)
            ->assertSuccessful();

        $this::assertCount(1, $this->repository->findAll());

        $webhookCall = $this->repository->first();

        $this::assertEquals('my.type', $webhookCall->payload()->toArray()['type']);
        $this::assertEquals($payload, $webhookCall->payload()->toArray());
        $this::assertEmpty($webhookCall->exception()->toArray());

        Event::assertDispatched('stripe-webhooks::my.type', function ($event, $eventPayload) use ($webhookCall) {
            $this::assertInstanceOf(WebhookCall::class, $eventPayload);
            $this::assertEquals($webhookCall->id, $eventPayload->id);

            return true;
        });

        $this::assertEquals($webhookCall->id, cache('dummyJob')->id);
    }

    /**
     * @test
     */
    public function a_request_without_signature_with_verification_disabled(): void
    {
        config(['stripe-webhooks.verify_signature' => false]);
        cache()->clear();

        $this->withoutExceptionHandling();

        $payload = [
            'type' => 'my.type',
            'key' => 'value',
        ];

        $headers = [];

        $this
            ->postJson('stripe-webhooks', $payload, $headers)
            ->assertSuccessful();

        $this::assertCount(1, $this->repository->findAll());

        $webhookCall = $this->repository->first();

        $this::assertEquals('my.type', $webhookCall->payload()->toArray()['type']);
        $this::assertEquals($payload, $webhookCall->payload()->toArray());
        $this::assertEmpty($webhookCall->exception()->toArray());

        Event::assertDispatched('stripe-webhooks::my.type', function ($event, $eventPayload) use ($webhookCall) {
            $this::assertInstanceOf(WebhookCall::class, $eventPayload);
            $this::assertEquals($webhookCall->id, $eventPayload->id);

            return true;
        });

        $this::assertEquals($webhookCall->id, cache('dummyJob')->id);
    }

    /**
     * @test
     */
    public function a_request_with_an_invalid_signature_wont_be_logged(): void
    {
        $payload = [
            'type' => 'my.type',
            'key' => 'value',
        ];

        $headers = ['Stripe-Signature' => 'invalid_signature'];

        $this
            ->postJson('stripe-webhooks', $payload, $headers)
            ->assertStatus(500);

        $this::assertCount(0, $this->repository->findAll());

        Event::assertNotDispatched('stripe-webhooks::my.type');

        $this::assertNull(cache('dummyJob'));
    }

    /**
     * @test
     *
     * @throws JsonException
     */
    public function a_request_with_an_invalid_payload_will_be_logged_but_events_and_jobs_will_not_be_dispatched(): void
    {
        $payload = ['invalid_payload'];

        $headers = ['Stripe-Signature' => $this->determineStripeSignature($payload)];

        $this
            ->postJson('stripe-webhooks', $payload, $headers)
            ->assertStatus(400);

        $this::assertCount(1, $this->repository->findAll());

        $webhookCall = $this->repository->first();

        $this::assertFalse(isset($webhookCall->payload()->toArray()['type']));
        $this::assertEquals(['invalid_payload'], $webhookCall->payload()->toArray());

        $this::assertEquals('Webhook call id `1` did not contain a type. Valid Stripe webhook calls should always contain a type.', $webhookCall->exception()->toArray()['message']);

        Event::assertNotDispatched('stripe-webhooks::my.type');

        $this::assertNull(cache('dummyJob'));
    }

    /**
     * @test
     *
     * @throws JsonException
     */
    public function a_request_with_a_config_key_will_use_the_correct_signing_secret(): void
    {
        config()->set('stripe-webhooks.signing_secret', 'secret1');
        config()->set('stripe-webhooks.signing_secret_somekey', 'secret2');

        $payload = [
            'type' => 'my.type',
            'key' => 'value',
        ];

        $headers = ['Stripe-Signature' => $this->determineStripeSignature($payload, 'somekey')];

        $this
            ->postJson('stripe-webhooks/somekey', $payload, $headers)
            ->assertSuccessful();
    }

    /**
     * @test
     *
     * @throws JsonException
     */
    public function a_request_will_only_be_processed_once(): void
    {
        $payload = [
            'type' => 'my.type',
            'id' => 'evt_123',
        ];

        $headers = ['Stripe-Signature' => $this->determineStripeSignature($payload)];

        $this
            ->postJson('stripe-webhooks', $payload, $headers)
            ->assertSuccessful();

        $this
            ->postJson('stripe-webhooks', $payload, $headers)
            ->assertSuccessful();

        $this::assertCount(1, $this->getEntityByPayloadId($payload['id']));
    }

    /**
     * @return array<WebhookCall>
     */
    private function getEntityByPayloadId(string $payloadId): array
    {
        return $this->repository
            ->select()
            ->where(['name' => 'stripe'])
            ->andWhere("JSON_EXTRACT(payload, '$.id')", '=', new Parameter(['payloadId' => $payloadId]))
            ->fetchAll();
    }
}
