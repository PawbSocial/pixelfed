<?php

namespace App\Services;

use App\Instance;
use App\Profile;
use App\Status;
use App\Story;
use App\Jobs\DeletePipeline\DeleteRemoteProfilePipeline;
use App\Jobs\HomeFeedPipeline\FeedRemoveRemotePipeline;
use App\Jobs\ProfilePipeline\HandleUpdateActivity;
use App\Jobs\StatusPipeline\RemoteStatusDelete;
use App\Jobs\StatusPipeline\StatusRemoteUpdatePipeline;
use App\Jobs\StoryPipeline\StoryExpire;
use App\Models\InstanceActor;
use App\Models\Relay;
use App\Util\ActivityPub\Helpers;
use App\Util\ActivityPub\HttpSignature;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RelayService
{
    protected $client;
    protected $timeout;

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => config('federation.activitypub.relay.delivery_timeout', 30),
        ]);
        $this->timeout = config('federation.activitypub.relay.delivery_timeout', 30);
    }

    /**
     * Add a new relay
     */
    public function addRelay(string $inboxUrl, ?string $name = null, bool $autoFollow = true): Relay
    {
        // Validate and normalize the inbox URL
        $inboxUrl = $this->normalizeInboxUrl($inboxUrl);

        if (!Helpers::validateUrl($inboxUrl)) {
            throw new \InvalidArgumentException('Invalid inbox URL provided');
        }

        // Check if relay already exists
        $existingRelay = Relay::where('inbox_url', $inboxUrl)->first();
        if ($existingRelay) {
            throw new \InvalidArgumentException('Relay already exists');
        }

        // Fetch relay actor information
        $actorUrl = $this->deriveActorUrl($inboxUrl);
        $metadata = $this->fetchRelayInfo($actorUrl);

        $relay = Relay::create([
            'name' => $name ?: ($metadata['name'] ?? null),
            'inbox_url' => $inboxUrl,
            'actor_url' => $actorUrl,
            'is_active' => false,
            'following' => false,
            'metadata' => $metadata,
        ]);

        Log::info('Relay added', ['relay_id' => $relay->id, 'inbox_url' => $inboxUrl]);

        // Auto-follow by default
        if ($autoFollow) {
            $this->followRelay($relay);
        }

        return $relay;
    }

    /**
     * Follow a relay
     */
    public function followRelay(Relay $relay): bool
    {
        if ($relay->following) {
            return true; // Already following
        }

        try {
            $instanceActor = InstanceActor::first();
            if (!$instanceActor) {
                throw new \Exception('Instance actor not found');
            }

            $activity = [
                '@context' => 'https://www.w3.org/ns/activitystreams',
                'id' => config('app.url') . '/i/actor#follow/' . $relay->id,
                'type' => 'Follow',
                'actor' => config('app.url') . '/i/actor',
                'object' => $relay->actor_url,
            ];

            $success = $this->sendSignedActivity($relay, $activity);

            if ($success) {
                $relay->update(['following' => true, 'is_active' => true]);
                Log::info('Successfully sent Follow activity to relay', ['relay_id' => $relay->id]);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Failed to follow relay', [
                'relay_id' => $relay->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Unfollow a relay
     */
    public function unfollowRelay(Relay $relay): bool
    {
        if (!$relay->following) {
            return true; // Not following
        }

        try {
            $activity = [
                '@context' => 'https://www.w3.org/ns/activitystreams',
                'id' => config('app.url') . '/i/actor#unfollow/' . $relay->id,
                'type' => 'Undo',
                'actor' => config('app.url') . '/i/actor',
                'object' => [
                    'id' => config('app.url') . '/i/actor#follow/' . $relay->id,
                    'type' => 'Follow',
                    'actor' => config('app.url') . '/i/actor',
                    'object' => $relay->actor_url,
                ],
            ];

            $success = $this->sendSignedActivity($relay, $activity);

            if ($success) {
                $relay->update(['following' => false]);
                Log::info('Successfully sent Undo Follow activity to relay', ['relay_id' => $relay->id]);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Failed to unfollow relay', [
                'relay_id' => $relay->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send an activity to relay
     */
    public function sendActivityToRelay(Relay $relay, array $activity): bool
    {
        if (!$relay->is_active || !$relay->following) {
            return false;
        }

        if (!$relay->isHealthy()) {
            Log::warning('Skipping delivery to unhealthy relay', ['relay_id' => $relay->id]);
            return false;
        }

        try {
            $success = $this->sendSignedActivity($relay, $activity);

            if ($success) {
                $relay->markSuccessfulDelivery();
                return true;
            } else {
                $relay->markFailedDelivery();
                return false;
            }
        } catch (\Exception $e) {
            $relay->markFailedDelivery();
            Log::error('Failed to send activity to relay', [
                'relay_id' => $relay->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send an activity to all active relays
     */
    public function sendActivityToAllRelays(array $activity): array
    {
        $relays = Relay::activeAndFollowing()->get();
        $results = [];

        foreach ($relays as $relay) {
            $results[$relay->id] = $this->sendActivityToRelay($relay, $activity);
        }

        return $results;
    }

    /**
     * Test relay connection
     */
    public function testRelay(Relay $relay): array
    {
        $results = [
            'reachable' => false,
            'actor_info' => null,
            'error' => null,
        ];

        try {
            // Test if we can fetch the actor
            $actorInfo = $this->fetchRelayInfo($relay->actor_url);
            $results['actor_info'] = $actorInfo;
            $results['reachable'] = true;

            // Update metadata if we got new info
            if ($actorInfo && $actorInfo !== $relay->metadata) {
                $relay->update(['metadata' => $actorInfo]);
            }
        } catch (\Exception $e) {
            $results['error'] = $e->getMessage();
            Log::warning('Relay test failed', [
                'relay_id' => $relay->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $results;
    }

    /**
     * Get all active relay inbox URLs for delivery
     */
    public function getActiveRelayInboxes(): array
    {
        return Cache::remember('relay:active_inboxes', 300, function () {
            return Relay::activeAndFollowing()
                ->pluck('inbox_url')
                ->toArray();
        });
    }

    /**
     * Verify incoming relay activity
     */
    public function verifyIncomingRelayActivity(array $headers, string $payload): ?Relay
    {
		$signature = is_array($headers['signature']) ? $headers['signature'][0] : $headers['signature'];
        $signatureData = HttpSignature::parseSignatureHeader($signature);

        if (! isset($signatureData)) {
            Log::warning('Incoming relay activity signature failed to parse', ['signature' => $signature]);
            return null;
        }

        $keyId = $signatureData['keyId'] ?? null;
        if (! isset($keyId)) {
            Log::warning('Incoming relay activity missing KeyId in signature', ['signatureData' => $signatureData]);
            return null;
        }

        $relay = Relay::where('actor_url', 'like', '%' . parse_url($keyId, PHP_URL_HOST) . '%')->first();
        if (!isset($relay) || !isset($relay->metadata['public_key'])) {
            return null;
        }

        $publicKey = $relay->metadata['public_key'];

        [$verified, $signingString] = HttpSignature::verify(
            $publicKey,
            $signatureData,
            $headers,
            $_SERVER['REQUEST_URI'] ?? '/',
            $payload
        );

        return $verified ? $relay : null;
    }

    /**
     * Process incoming relay activity (Follow, Undo, etc.)
     */
    public function processIncomingRelayActivity(array $activity, Relay $relay): bool
    {
        if (!isset($activity['type'], $activity['actor'])) {
            return false;
        }

        switch ($activity['type']) {
            case 'Follow':
                return $this->handleRelayFollow($relay, $activity);

            case 'Accept':
                return $this->handleRelayAccept($relay, $activity);

            case 'Announce':
                return $this->handleRelayAnnounce($relay, $activity);

            case 'Undo':
                return $this->handleRelayUndo($relay, $activity);

            case 'Update':
                return $this->handleRelayUpdate($relay, $activity);

            case 'Delete':
                return $this->handleRelayDelete($relay, $activity);

            default:
                Log::info('Unsupported relay activity type', ['type' => $activity['type']]);
                return false;
        }
    }

    /**
     * Handle Follow activity from relay
     */
    protected function handleRelayFollow(Relay $relay, array $activity): bool
    {
        // Send Accept response
        $accept = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => config('app.url') . '/i/actor#accept/' . md5($activity['id']),
            'type' => 'Accept',
            'actor' => config('app.url') . '/i/actor',
            'object' => $activity,
        ];

        $success = $this->sendSignedActivity($relay, $accept);

        if ($success) {
            $relay->update(['following' => true, 'is_active' => true]);
            Log::info('Accepted follow from relay', ['relay_id' => $relay->id]);
        }

        return $success;
    }

    /**
     * Handle Undo activity from relay
     */
    protected function handleRelayUndo(Relay $relay, array $activity): bool
    {
        if (isset($activity['object']['type']) && $activity['object']['type'] === 'Follow') {
            $relay->update(['following' => false]);
            Log::info('Relay unfollowed us', ['relay_id' => $relay->id]);
            return true;
        }

        return false;
    }

    /**
     * Handle Update activity from relay
     */
    protected function handleRelayUpdate(Relay $relay, array $activity): bool
    {
        if (! isset($activity['type'], $activity['id'])) {
            return false;
        }

        if (! Helpers::validateUrl($activity['id'])) {
            return false;
        }

        if ($activity['type'] === 'Note') {
            if (Status::whereObjectUrl($activity['id'])->exists()) {
                StatusRemoteUpdatePipeline::dispatch($activity['id'], $activity);
            }
        } elseif ($activity['type'] === 'Person') {
            if (UpdatePersonValidator::validate($this->payload)) {
                HandleUpdateActivity::dispatch($activity)->onQueue('low');
            }
        }
    }

    /**
     * Handle Delete activity from relay
     */
    protected function handleRelayDelete(Relay $relay, array $activity): bool
    {
        $actor = $activity['actor'] ?? null;
        $object = $activity['object'] ?? null;

        if (! isset($actor, $object)) {
            return false;
        }

        /**
         * Mostly copied across from Inbox.php...
         */
        if (is_string($object) == true && $actor == $object && Helpers::validateUrl($object)) {
            // Profile deletion
            $profile = Profile::whereRemoteUrl($object)->first();
            if (! $profile || $profile->private_key != null) {
                return false;
            }
            DeleteRemoteProfilePipeline::dispatch($profile)->onQueue('low');

            return true;
        } else {
            if (! isset($object['id'], $object['type'])) {
                return false;
            }

            $type = $object['type'];
            $typeCheck = in_array($type, ['Person', 'Tombstone', 'Story']);

            if (! Helpers::validateUrl($actor) || ! Helpers::validateUrl($object['id']) || ! $typeCheck) {
                return false;
            }

            if (parse_url($object['id'], PHP_URL_HOST) != parse_url($actor, PHP_URL_HOST)) {
                return false;
            }

            $id = $object['id'];

            switch ($type) {
                case 'Person':
                    $profile = Profile::whereRemoteUrl($actor)->first();
                    if (! $profile || $profile->private_key != null) {
                        return false;
                    }
                    DeleteRemoteProfilePipeline::dispatch($profile)->onQueue('low');

                    return true;

                case 'Tombstone':
                    $profile = Profile::whereRemoteUrl($actor)->first();
                    if (! $profile || $profile->private_key != null) {
                        return false;
                    }

                    $status = Status::where('object_url', $id)->first();
                    if (! $status) {
                        $status = Status::where('url', $id)->first();
                        if (! $status) {
                            return false;
                        }
                    }

                    if ($status->profile_id != $profile->id) {
                        return false;
                    }

                    if ($status->scope && in_array($status->scope, ['public', 'unlisted', 'private'])) {
                        if ($status->type && ! in_array($status->type, ['story:reaction', 'story:reply', 'reply'])) {
                            FeedRemoveRemotePipeline::dispatch($status->id, $status->profile_id)->onQueue('feed');
                        }
                    }

                    RemoteStatusDelete::dispatch($status)->onQueue('low');

                    return true;

                case 'Story':
                    $story = Story::whereObjectId($id)->first();

                    if (!$story) {
                        return false;
                    }

                    StoryExpire::dispatch($story)->onQueue('story');
                    return true;

                default:
                    return false;
            }
        }
    }

    /**
     * Handle Accept activity from relay
     */
    protected function handleRelayAccept(Relay $relay, array $activity): bool
    {
        // Relay accepted our follow request
        if (isset($activity['object']['type']) && $activity['object']['type'] === 'Follow') {
            $relay->update(['following' => true, 'is_active' => true]);
            Log::info('Relay accepted our follow request', ['relay_id' => $relay->id]);
            return true;
        }

        return false;
    }

    /**
     * Handle Announce activity from relay (content forwarding)
     */
    protected function handleRelayAnnounce(Relay $relay, array $activity): bool
    {
        if (!isset($activity['object'])) {
            return false;
        }

        $objectUrl = $activity['object'];
        Log::info('Received content announce from relay', [
            'relay_id' => $relay->id,
            'object_url' => $objectUrl,
            'activity_id' => $activity['id'] ?? 'unknown'
        ]);

        try {
            // Fetch the original content that was announced
            $status = Helpers::statusFetch($objectUrl);

            if ($status) {
                Log::info('Successfully processed relay content', [
                    'relay_id' => $relay->id,
                    'status_id' => $status->id,
                    'object_url' => $objectUrl,
                    'author' => $status->profile->username
                ]);

                $domain = $status->profile->domain;

                if (Instance::moderated()->whereDomain($domain)->exists()) {
                    Log::info('Content from moderated instance - skipping relay delivery', [
                        'relay_id' => $relay->id,
                        'domain' => $domain,
                        'status_id' => $status->id
                    ]);
                    return false;
                }

                // Update relay health tracking
                $relay->update([
                    'last_successful_delivery_at' => now(),
                    'failed_delivery_count' => 0
                ]);

                return true;
            } else {
                Log::warning('Relay announced content but statusFetch returned null', [
                    'relay_id' => $relay->id,
                    'object_url' => $objectUrl
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to process relay content', [
                'relay_id' => $relay->id,
                'object_url' => $objectUrl,
                'error' => $e->getMessage()
            ]);

            // Track failed delivery
            $relay->increment('failed_delivery_count');
            $relay->update(['last_failed_delivery_at' => now()]);
        }

        return false;
    }

    /**
     * Send signed activity to relay
     */
    protected function sendSignedActivity(Relay $relay, array $activity): bool
    {
        $instanceActor = InstanceActor::first();
        if (!$instanceActor) {
            throw new \Exception('Instance actor not found');
        }

        // TODO: Consider Helpers::sendSignedObject() refactor

        $keyId = config('app.url') . '/i/actor#main-key';
        $payload = json_encode($activity);

        $headers = HttpSignature::signRaw(
            $instanceActor->private_key,
            $keyId,
            $relay->inbox_url,
            $activity,
            [
                'Content-Type' => 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"',
                'User-Agent' => $this->getUserAgent(),
            ]
        );

        try {
            $response = $this->client->post($relay->inbox_url, [
                'curl' => [
                    CURLOPT_HTTPHEADER => $headers,
                    CURLOPT_POSTFIELDS => $payload,
                    CURLOPT_HEADER => true,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSL_VERIFYHOST => false,
                ],
                'timeout' => $this->timeout,
            ]);

            return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
        } catch (ClientException $e) {
            if ($e->getResponse() && $e->getResponse()->getStatusCode() === 202) {
                return true; // 202 is acceptable for ActivityPub
            }
            throw $e;
        }
    }

    /**
     * Fetch relay actor information
     */
    protected function fetchRelayInfo(string $actorUrl): ?array
    {
        try {
            $response = $this->client->get($actorUrl, [
                'headers' => [
                    'Accept' => 'application/ld+json; profile="https://www.w3.org/ns/activitystreams", application/activity+json',
                    'User-Agent' => $this->getUserAgent(),
                ],
                'timeout' => 15,
            ]);

            $data = json_decode($response->getBody(), true);

            return [
                'type' => $data['type'] ?? null,
                'name' => $data['name'] ?? $data['preferredUsername'] ?? null,
                'summary' => $data['summary'] ?? null,
                'inbox' => $data['inbox'] ?? null,
                'outbox' => $data['outbox'] ?? null,
                'endpoints' => $data['endpoints'] ?? null,
                'public_key' => $data['publicKey']['publicKeyPem'] ?? null,
                'software' => $this->detectRelaySoftware($data),
            ];
        } catch (\Exception $e) {
            Log::warning('Failed to fetch relay info', [
                'actor_url' => $actorUrl,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Detect relay software type
     */
    protected function detectRelaySoftware(?array $actorData): ?string
    {
        if (!$actorData) {
            return 'unknown';
        }

        if (isset($actorData['software'])) {
            return $actorData['software'];
        }

        // Try to detect AodeRelay
        if (isset($actorData['name']) && str_contains(strtolower($actorData['name']), 'aode')) {
            return 'aoderelay';
        }

        // Try to detect by summary or other fields
        if (isset($actorData['summary'])) {
            $summary = strtolower($actorData['summary']);
            if (str_contains($summary, 'aode') || str_contains($summary, 'relay')) {
                return 'aoderelay';
            }
        }

        return 'unknown';
    }

    /**
     * Normalize inbox URL
     */
    protected function normalizeInboxUrl(string $url): string
    {
        // Remove trailing slash
        $url = rtrim($url, '/');

        // Ensure it ends with /inbox for relays
        if (!str_ends_with($url, '/inbox')) {
            $url .= '/inbox';
        }

        return $url;
    }

    /**
     * Derive actor URL from inbox URL
     */
    public function deriveActorUrl(string $inboxUrl): string
    {
        // For most relays, the actor URL follows the pattern:
        // Inbox: https://relay.example.com/inbox
        // Actor: https://relay.example.com/actor
        if (str_ends_with($inboxUrl, '/inbox')) {
            return str_replace('/inbox', '/actor', $inboxUrl);
        }

        // Fallback: assume actor is at the base URL
        return rtrim($inboxUrl, '/') . '/actor';
    }

    /**
     * Get user agent string
     */
    protected function getUserAgent(): string
    {
        $version = config('pixelfed.version', '0.12.0');
        $appUrl = config('app.url');
        return "(Pixelfed/{$version}; +{$appUrl})";
    }

    /**
     * Clear relay cache
     */
    public function clearCache(): void
    {
        Cache::forget('relay:active_inboxes');
    }
}
