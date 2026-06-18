<?php

declare(strict_types=1);

namespace Sendara\Laravel;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use Sendara\Client;
use Sendara\Exception\SendaraException;

final class SendaraServiceProvider extends ServiceProvider
{
    private const CONFIG_PATH = __DIR__ . '/../../config/sendara.php';

    public function register(): void
    {
        $this->mergeConfigFrom(self::CONFIG_PATH, 'sendara');

        $this->app->singleton(Client::class, static function (Container $app): Client {
            $config = $app->make('config')->get('sendara', []);

            $apiKey = $config['api_key'] ?? env('SENDARA_API_KEY');
            if (!is_string($apiKey) || $apiKey === '') {
                throw new SendaraException(
                    'Sendara API key is missing. Set SENDARA_API_KEY or config("sendara.api_key").'
                );
            }

            $options = [];

            $baseUrl = $config['base_url'] ?? null;
            if (is_string($baseUrl) && $baseUrl !== '') {
                $options['baseUrl'] = $baseUrl;
            }

            if (isset($config['timeout']) && $config['timeout'] !== null) {
                $options['timeout'] = (int) $config['timeout'];
            }

            if (isset($config['max_retries']) && $config['max_retries'] !== null) {
                $options['maxRetries'] = (int) $config['max_retries'];
            }

            return new Client($apiKey, $options);
        });

        $this->app->alias(Client::class, 'sendara');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                self::CONFIG_PATH => $this->app->configPath('sendara.php'),
            ], 'sendara-config');
        }
    }

    /**
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [Client::class, 'sendara'];
    }
}
