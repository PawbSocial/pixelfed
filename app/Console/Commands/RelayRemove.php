<?php

namespace App\Console\Commands;

use App\Models\Relay;
use App\Services\RelayService;
use Illuminate\Console\Command;

class RelayRemove extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'relay:remove {id} {--force : Force removal without unfollowing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove an ActivityPub relay';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $relayId = $this->argument('id');
        $force = $this->option('force');

        $relay = Relay::find($relayId);
        if (!$relay) {
            $this->error("Relay with ID {$relayId} not found.");
            return 1;
        }

        $this->info("Removing relay:");
        $this->line("  ID: {$relay->id}");
        $this->line("  Name: {$relay->display_name}");
        $this->line("  Inbox URL: {$relay->inbox_url}");
        $this->line("  Following: " . ($relay->following ? 'Yes' : 'No'));

        if (!$force && !$this->confirm('Are you sure you want to remove this relay?')) {
            $this->info('Cancelled.');
            return 0;
        }

        try {
            $relayService = new RelayService();

            // Unfollow the relay first if we're following it
            if ($relay->following && !$force) {
                $this->info('Unfollowing relay...');
                $success = $relayService->unfollowRelay($relay);

                if (!$success) {
                    $this->warn('Failed to send unfollow activity, but continuing with removal.');
                }
            }

            // Remove the relay from database
            $relay->delete();

            // Clear relay cache
            $relayService->clearCache();

            $this->info('✓ Relay removed successfully');
            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to remove relay: {$e->getMessage()}");
            return 1;
        }
    }
}
