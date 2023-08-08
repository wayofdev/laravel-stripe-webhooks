<?php

declare(strict_types=1);

namespace WayOfDev\StripeWebhooks\Tests;

use DateTimeImmutable;
use Illuminate\Support\Facades\Event;
use WayOfDev\StripeWebhooks\App\Jobs\DummyJob;
use WayOfDev\StripeWebhooks\Bridge\Laravel\Jobs\ProcessStripeWebhookJob;
use WayOfDev\StripeWebhooks\Exceptions\WebhookFailed;
use WayOfDev\WebhookClient\Entities\Exception;
use WayOfDev\WebhookClient\Entities\Headers;
use WayOfDev\WebhookClient\Entities\Payload;
use WayOfDev\WebhookClient\Entities\WebhookCall;

final class StripeWebhookCallTest extends TestCase
{
    private ProcessStripeWebhookJob $processStripeWebhookJob;

    private WebhookCall $webhookCall;

    public function setUp(): void
    {
        parent::setUp();

        Event::fake();

        config(['stripe-webhooks.jobs' => ['my_type' => DummyJob::class]]);

        $this->webhookCall = $this->createEntity();
        $this->processStripeWebhookJob = new ProcessStripeWebhookJob($this->webhookCall);
    }

    /**
     * @test
     *
     * @throws WebhookFailed
     */
    public function it_will_fire_off_the_configured_job(): void
    {
        $this->processStripeWebhookJob->handle();

        $this::assertEquals($this->webhookCall->id, cache('dummyJob')->id);
    }

    /**
     * @test
     *
     * @throws WebhookFailed
     */
    public function it_will_not_dispatch_a_job_for_another_type(): void
    {
        config(['stripe-webhooks.jobs' => ['another_type' => DummyJob::class]]);

        $this->processStripeWebhookJob->handle();

        $this::assertNull(cache('dummyJob'));
    }

    /**
     * @test
     *
     * @throws WebhookFailed
     */
    public function it_will_not_dispatch_jobs_when_no_jobs_are_configured(): void
    {
        config(['stripe-webhooks.jobs' => []]);

        $this->processStripeWebhookJob->handle();

        $this::assertNull(cache('dummyJob'));
    }

    /**
     * @test
     *
     * @throws WebhookFailed
     */
    public function it_will_dispatch_jobs_when_default_job_is_configured(): void
    {
        config([
            'stripe-webhooks.jobs' => [],
            'stripe-webhooks.default_job' => DummyJob::class,
        ]);

        $this->processStripeWebhookJob->handle();

        $this::assertEquals($this->webhookCall->id, cache('dummyJob')->id);
    }

    /**
     * @test
     *
     * @throws WebhookFailed
     */
    public function it_will_dispatch_events_even_when_no_corresponding_job_is_configured(): void
    {
        config(['stripe-webhooks.jobs' => ['another_type' => DummyJob::class]]);

        $this->processStripeWebhookJob->handle();

        $webhookCall = $this->webhookCall;

        Event::assertDispatched("stripe-webhooks::{$webhookCall->payload()->toArray()['type']}", function ($event, $eventPayload) use ($webhookCall) {
            $this::assertInstanceOf(WebhookCall::class, $eventPayload);
            $this::assertEquals($webhookCall->id, $eventPayload->id);

            return true;
        });

        $this::assertNull(cache('dummyJob'));
    }

    /**
     * @test
     */
    public function it_can_specify_a_connection_in_the_config(): void
    {
        config(['stripe-webhooks.connection' => 'some-connection']);

        $processStripeWebhookJob = new ProcessStripeWebhookJob($this->webhookCall);

        $this::assertEquals('some-connection', $processStripeWebhookJob->connection);
    }

    /**
     * @test
     */
    public function it_can_specify_a_queue_in_the_config(): void
    {
        config(['stripe-webhooks.queue' => 'some-queue']);

        $processStripeWebhookJob = new ProcessStripeWebhookJob($this->webhookCall);

        $this::assertEquals('some-queue', $processStripeWebhookJob->queue);
    }

    private function createEntity(): WebhookCall
    {
        $entity = new WebhookCall(
            name: 'stripe',
            url: '/stripe',
            headers: Headers::fromArray([]),
            payload: Payload::fromArray(['type' => 'my.type', 'name' => 'value']),
            exception: Exception::fromArray([]),
            createdAt: new DateTimeImmutable(),
        );

        $this->repository->persist($entity);

        return $entity;
    }
}
