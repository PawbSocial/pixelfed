<?php

namespace App\Console\Commands;

use App\Models\Relay;
use App\Services\RelayService;
use Illuminate\Console\Command;

class RelayUnfollow extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'relay:unfollow {relay : The relay ID or inbox URL to unfollow}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Unfollow a relay';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (!config('federation.activitypub.relay.enabled', false)) {
            $this->error('Relay support is not enabled. Check your federation.activitypub.relay.enabled configuration.');
            return 1;
        }

        $identifier = $this->argument('relay');
        
        // Try to find relay by ID first, then by inbox URL
        $relay = null;
        if (is_numeric($identifier)) {
            $relay = Relay::find($identifier);
        }
        
        if (!$relay) {
            $relay = Relay::where('inbox_url', $identifier)->first();
        }

        if (!$relay) {
            $this->error("Relay not found: {$identifier}");
            $this->comment('Available relays:');
            
            $relays = Relay::all();
            if ($relays->isEmpty()) {
                $this->line('  No relays configured');
            } else {
                foreach ($relays as $r) {
                    $this->line("  ID: {$r->id} - {$r->display_name} ({$r->inbox_url})");
                }
            }
            return 1;
        }

        if (!$relay->following) {
            $this->info("Not currently following relay: {$relay->display_name}");
            return 0;
        }

        $this->info("Unfollowing relay: {$relay->display_name} ({$relay->inbox_url})");

        if (!$this->confirm('Are you sure you want to unfollow this relay?')) {
            $this->comment('Operation cancelled.');
            return 0;
        }

        try {
            $relayService = new RelayService();
            $success = $relayService->unfollowRelay($relay);

            if ($success) {
                $this->info('✓ Successfully unfollowed the relay');
                return 0;
            } else {
                $this->error('✗ Failed to unfollow the relay. Check the logs for details.');
                return 1;
            }
        } catch (\Exception $e) {
            $this->error("Error unfollowing relay: {$e->getMessage()}");
            return 1;
        }
    }
}