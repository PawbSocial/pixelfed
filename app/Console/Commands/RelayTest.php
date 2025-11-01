<?php

namespace App\Console\Commands;

use App\Models\Relay;
use App\Services\RelayService;
use Illuminate\Console\Command;

class RelayTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'relay:test {id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test connection to a relay';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $relayId = $this->argument('id');

        $relay = Relay::find($relayId);
        if (!$relay) {
            $this->error("Relay with ID {$relayId} not found.");
            return 1;
        }

        $this->info("Testing relay:");
        $this->line("  ID: {$relay->id}");
        $this->line("  Name: {$relay->display_name}");
        $this->line("  Inbox URL: {$relay->inbox_url}");
        $this->line("  Actor URL: {$relay->actor_url}");
        $this->line('');

        $relayService = new RelayService();

        $this->info('Testing connection...');
        $results = $relayService->testRelay($relay);

        if ($results['reachable']) {
            $this->info('✓ Relay is reachable');

            if ($results['actor_info']) {
                $this->line('');
                $this->info('Actor Information:');
                $actorInfo = $results['actor_info'];

                if (isset($actorInfo['type'])) {
                    $this->line("  Type: {$actorInfo['type']}");
                }
                if (isset($actorInfo['name'])) {
                    $this->line("  Name: {$actorInfo['name']}");
                }
                if (isset($actorInfo['summary'])) {
                    $this->line("  Summary: {$actorInfo['summary']}");
                }
                if (isset($actorInfo['software'])) {
                    $this->line("  Software: {$actorInfo['software']}");
                }
                if (isset($actorInfo['inbox'])) {
                    $this->line("  Inbox: {$actorInfo['inbox']}");
                }
                if (isset($actorInfo['outbox'])) {
                    $this->line("  Outbox: {$actorInfo['outbox']}");
                }
            }

            $this->line('');
            $this->info('Relay Status:');
            $this->line("  Active: " . ($relay->is_active ? 'Yes' : 'No'));
            $this->line("  Following: " . ($relay->following ? 'Yes' : 'No'));
            $this->line("  Healthy: " . ($relay->isHealthy() ? 'Yes' : 'No'));
            $this->line("  Failed deliveries: {$relay->failed_delivery_count}");

            if ($relay->last_successful_delivery_at) {
                $this->line("  Last successful delivery: {$relay->last_successful_delivery_at->diffForHumans()}");
            } else {
                $this->line("  Last successful delivery: Never");
            }

            if ($relay->last_failed_delivery_at) {
                $this->line("  Last failed delivery: {$relay->last_failed_delivery_at->diffForHumans()}");
            }

            return 0;
        } else {
            $this->error('✗ Relay is not reachable');
            if ($results['error']) {
                $this->error("Error: {$results['error']}");
            }
            return 1;
        }
    }
}
