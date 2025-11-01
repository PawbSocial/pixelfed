<?php

namespace App\Console\Commands;

use App\Models\Relay;
use App\Services\RelayService;
use Illuminate\Console\Command;

class RelayList extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'relay:list {--json : Output as JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all configured relays';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $relays = Relay::orderBy('id')->get();

        if ($this->option('json')) {
            $this->line(json_encode($relays->toArray(), JSON_PRETTY_PRINT));
            return 0;
        }

        if ($relays->isEmpty()) {
            $this->info('No relays configured.');
            return 0;
        }

        $this->info('Configured Relays:');
        $this->line('');

        $headers = ['ID', 'Name', 'Domain', 'Active', 'Following', 'Health', 'Last Success'];
        $rows = [];

        foreach ($relays as $relay) {
            $domain = parse_url($relay->inbox_url, PHP_URL_HOST);
            $health = $relay->isHealthy() ? '✓' : '✗';
            $lastSuccess = $relay->last_successful_delivery_at
                ? $relay->last_successful_delivery_at->diffForHumans()
                : 'Never';

            $rows[] = [
                $relay->id,
                $relay->display_name ?: 'N/A',
                $domain,
                $relay->is_active ? '✓' : '✗',
                $relay->following ? '✓' : '✗',
                $health,
                $lastSuccess,
            ];
        }

        $this->table($headers, $rows);

        // Show summary
        $activeCount = $relays->where('is_active', true)->count();
        $followingCount = $relays->where('following', true)->count();
        $healthyCount = $relays->filter(fn($r) => $r->isHealthy())->count();

        $this->line('');
        $this->info("Summary: {$relays->count()} total, {$activeCount} active, {$followingCount} following, {$healthyCount} healthy");

        return 0;
    }
}
