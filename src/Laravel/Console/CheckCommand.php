<?php

namespace Darory\WhatsApp\Laravel\Console;

use Darory\WhatsApp\Exceptions\DaroryWhatsAppException;
use Darory\WhatsApp\Laravel\WhatsAppManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;

class CheckCommand extends Command
{
    protected $signature = 'darory-whatsapp:check {instance? : A configured instance name (the default one when empty)}';

    protected $description = 'Check the Darory WhatsApp credentials and connection, and print the webhook URL';

    public function handle(WhatsAppManager $manager): int
    {
        $name = $this->argument('instance') ?? $manager->defaultInstance();

        try {
            $client = $manager->instance($name);
            $session = $client->session();
        } catch (InvalidArgumentException|DaroryWhatsAppException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('Instance', "{$name} (#{$client->instanceId()})");
        $this->components->twoColumnDetail('Status', $session->isConnected() ? '<fg=green>connected</>' : '<fg=yellow>'.$session->status.'</>');

        if ($session->isConnected()) {
            $this->components->twoColumnDetail('Number', (string) $session->number);
            $this->components->twoColumnDetail('Name', (string) $session->name);
        } else {
            $this->components->warn('The number is not connected. Scan the QR code in the Darory client area, or use WhatsApp::pairingCode($number).');
        }

        if (Route::has('darory-whatsapp.webhook')) {
            $this->newLine();
            $this->components->info('Add this URL as a "message" webhook on the instance to receive messages:');
            $this->line('  '.$manager->webhookUrl());

            if (! config('darory-whatsapp.webhook.secret')) {
                $this->components->warn('Set DARORY_WA_WEBHOOK_SECRET so only the gateway can call this URL.');
            }
        }

        return self::SUCCESS;
    }
}
