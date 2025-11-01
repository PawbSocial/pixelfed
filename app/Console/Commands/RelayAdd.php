<?php

namespace App\Console\Commands;

use App\Models\Relay;
use App\Services\RelayService;
use Illuminate\Console\Command;

class RelayAdd extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'relay:add {inbox_url} {--name= : Optional name for the relay}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add a new ActivityPub relay';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $inboxUrl = $this->argument('inbox_url');
        $name = $this->option('name');

        if (!config('federation.activitypub.relay.enabled', false)) {
            $this->error('Relay functionality is not enabled. Set AP_RELAY_ENABLED=true in your .env file.');
            return 1;
        }

        $this->info("Adding relay: {$inboxUrl}");

        try {
            $relayService = new RelayService();
            
            // Ask if they want to auto-follow (default: yes)
            $autoFollow = $this->confirm('Automatically follow this relay?', true);
            
            $relay = $relayService->addRelay($inboxUrl, $name, $autoFollow);

            $this->info("Successfully added relay:");
            $this->line("  ID: {$relay->id}");
            $this->line("  Name: {$relay->display_name}");
            $this->line("  Inbox URL: {$relay->inbox_url}");
            $this->line("  Actor URL: {$relay->actor_url}");
            $this->line("  Status: " . ($relay->is_active ? 'Active' : 'Inactive'));
            $this->line("  Following: " . ($relay->following ? 'Yes' : 'No'));

            if ($autoFollow && $relay->following) {
                $this->info('✓ Successfully followed the relay');
            } elseif (!$autoFollow) {
                $this->comment('Relay added but not followed. Use relay:follow to follow it later.');
            }

            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to add relay: {$e->getMessage()}");
            return 1;
        }
    }
}
