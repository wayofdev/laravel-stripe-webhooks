<?php

declare(strict_types=1);

namespace WayOfDev\StripeWebhooks\Tests;

use Cycle\ORM\ORMInterface;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use JsonException;
use Orchestra\Testbench\TestCase as Orchestra;
use WayOfDev\Cycle\Bridge\Laravel\Providers\CycleServiceProvider;
use WayOfDev\Cycle\Testing\Concerns\InteractsWithDatabase;
use WayOfDev\Cycle\Testing\RefreshDatabase;
use WayOfDev\StripeWebhooks\Bridge\Laravel\Providers\StripeWebhooksServiceProvider;
use WayOfDev\WebhookClient\Bridge\Laravel\Providers\WebhookClientServiceProvider;
use WayOfDev\WebhookClient\Persistence\ORMWebhookCallRepository;

use function array_merge;
use function hash_hmac;
use function json_encode;
use function time;

abstract class TestCase extends Orchestra
{
    use InteractsWithDatabase;
    use RefreshDatabase;

    protected ?string $migrationsPath = null;

    protected ORMWebhookCallRepository $repository;

    public function setUp(): void
    {
        parent::setUp();

        $this->migrationsPath = __DIR__ . '/../database/migrations/cycle';
        $this->cleanupMigrations($this->migrationsPath . '/*.php');
        $this->refreshDatabase();

        if (app()->environment() === 'testing') {
            config()->set([
                'cycle.tokenizer.directories' => array_merge(
                    config('cycle.tokenizer.directories'),
                    [__DIR__ . '/../../vendor/wayofdev/laravel-webhook-client/src/Entities'],
                ),
                'cycle.migrations.directory' => $this->migrationsPath,
            ]);
        }

        Artisan::call('cycle:migrate:init');
        Artisan::call('cycle:migrate', ['--force' => true]);
        Artisan::call('cycle:orm:migrate', ['--run' => true]);

        $this->repository = $this->resolveWebhookCallRepository();
    }

    protected function getPackageProviders($app): array
    {
        return [
            CycleServiceProvider::class,
            WebhookClientServiceProvider::class,
            StripeWebhooksServiceProvider::class,
        ];
    }

    /**
     * Set up the environment.
     *
     * @param Application $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        config(['stripe-webhooks.signing_secret' => 'test_signing_secret']);
    }

    /**
     * @throws JsonException
     */
    protected function determineStripeSignature(array $payload, ?string $configKey = null): string
    {
        $secret = (null !== $configKey) ?
            config("stripe-webhooks.signing_secret_{$configKey}") :
            config('stripe-webhooks.signing_secret');

        $timestamp = time();

        $timestampedPayload = $timestamp . '.' . json_encode($payload, JSON_THROW_ON_ERROR);

        $signature = hash_hmac('sha256', $timestampedPayload, $secret);

        return "t={$timestamp},v1={$signature}";
    }

    protected function resolveWebhookCallRepository(): ORMWebhookCallRepository
    {
        /** @var ORMWebhookCallRepository $repository */
        $repository = app(ORMInterface::class)
            ->getRepository(
                config('webhook-client.configs.0.webhook_entity')
            );

        return $repository;
    }
}
