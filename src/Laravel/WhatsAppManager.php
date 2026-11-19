<?php

namespace Darory\WhatsApp\Laravel;

use Darory\WhatsApp\Contracts\WhatsAppClient;
use Darory\WhatsApp\DaroryWhatsApp;
use Darory\WhatsApp\Testing\FakeWhatsApp;
use GuzzleHttp\ClientInterface;
use InvalidArgumentException;

/**
 * Resolves the configured instances. Calls on the manager go to the default instance.
 *
 * @mixin DaroryWhatsApp
 */
class WhatsAppManager
{
    /**
     * @var array<string, WhatsAppClient>
     */
    protected array $instances = [];

    protected ?FakeWhatsApp $fake = null;

    /**
     * @param  array<string, mixed>  $config  The darory-whatsapp config.
     */
    public function __construct(
        protected array $config,
        protected ?ClientInterface $http = null,
    ) {}

    /**
     * A configured instance by name, e.g. WhatsApp::instance('sales'). Null means the default one.
     */
    public function instance(?string $name = null): WhatsAppClient
    {
        $name ??= $this->defaultInstance();

        if ($this->fake !== null) {
            return $this->fake->forInstance($name);
        }

        return $this->instances[$name] ??= $this->build($name);
    }

    /**
     * A client for credentials you store yourself, e.g. one number per customer in a SaaS.
     */
    public function using(string $token, string|int $instanceId): WhatsAppClient
    {
        if ($this->fake !== null) {
            return $this->fake->forInstance((string) $instanceId);
        }

        return new DaroryWhatsApp($token, $instanceId, $this->baseUrl(), $this->timeout(), $this->http);
    }

    public function defaultInstance(): string
    {
        return (string) ($this->config['default'] ?? 'default');
    }

    /**
     * Send nothing; record every call for assertions instead.
     */
    public function fake(?FakeWhatsApp $fake = null): FakeWhatsApp
    {
        return $this->fake = $fake ?? new FakeWhatsApp;
    }

    public function isFake(): bool
    {
        return $this->fake !== null;
    }

    /**
     * The URL to register as the "message" webhook, including the secret.
     */
    public function webhookUrl(): string
    {
        $secret = $this->config['webhook']['secret'] ?? null;

        return route('darory-whatsapp.webhook', $secret ? ['secret' => $secret] : []);
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->instance()->{$method}(...$arguments);
    }

    protected function build(string $name): WhatsAppClient
    {
        $instance = $this->config['instances'][$name] ?? null;

        if (! is_array($instance)) {
            throw new InvalidArgumentException("The Darory WhatsApp instance [{$name}] is not configured in config/darory-whatsapp.php.");
        }

        if (empty($instance['token']) || empty($instance['instance_id'])) {
            throw new InvalidArgumentException("The Darory WhatsApp instance [{$name}] needs a token and an instance_id. Set DARORY_WA_TOKEN and DARORY_WA_INSTANCE_ID in your .env file.");
        }

        return new DaroryWhatsApp(
            (string) $instance['token'],
            $instance['instance_id'],
            (string) ($instance['base_url'] ?? $this->baseUrl()),
            $this->timeout(),
            $this->http,
        );
    }

    protected function baseUrl(): string
    {
        return (string) ($this->config['base_url'] ?? DaroryWhatsApp::DEFAULT_BASE_URL);
    }

    protected function timeout(): float
    {
        return (float) ($this->config['timeout'] ?? 60);
    }
}
