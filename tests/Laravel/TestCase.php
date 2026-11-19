<?php

namespace Darory\WhatsApp\Tests\Laravel;

use Darory\WhatsApp\Laravel\WhatsAppServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [WhatsAppServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['WhatsApp' => \Darory\WhatsApp\Laravel\Facades\WhatsApp::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('darory-whatsapp.instances', [
            'default' => ['token' => 'main-token', 'instance_id' => '12'],
            'sales' => ['token' => 'sales-token', 'instance_id' => '34'],
        ]);
        $app['config']->set('darory-whatsapp.webhook.secret', 'hook-secret');
    }
}
