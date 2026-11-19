<?php

namespace Darory\WhatsApp\Laravel;

use Darory\WhatsApp\Contracts\WhatsAppClient;
use Darory\WhatsApp\Laravel\Console\CheckCommand;
use Darory\WhatsApp\Laravel\Http\WebhookController;
use Darory\WhatsApp\Laravel\Notifications\WhatsAppChannel;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class WhatsAppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/darory-whatsapp.php', 'darory-whatsapp');

        $this->app->singleton(WhatsAppManager::class, fn (Application $app): WhatsAppManager => new WhatsAppManager(
            (array) $app['config']->get('darory-whatsapp', []),
        ));

        $this->app->bind(WhatsAppClient::class, fn (Application $app): WhatsAppClient => $app->make(WhatsAppManager::class)->instance());

        $this->app->afterResolving(ChannelManager::class, function (ChannelManager $channels): void {
            $channels->extend('whatsapp', fn (Application $app): WhatsAppChannel => $app->make(WhatsAppChannel::class));
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/darory-whatsapp.php' => config_path('darory-whatsapp.php'),
            ], 'darory-whatsapp-config');

            $this->commands([CheckCommand::class]);
        }

        if (config('darory-whatsapp.webhook.enabled', true) && ! $this->app->routesAreCached()) {
            Route::post((string) config('darory-whatsapp.webhook.path', 'darory-whatsapp/webhook'), WebhookController::class)
                ->middleware((array) config('darory-whatsapp.webhook.middleware', []))
                ->name('darory-whatsapp.webhook');
        }
    }
}
