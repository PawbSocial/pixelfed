<?php

namespace App\Http\Controllers;

use App\Models\InstanceActor;
use App\Services\RelayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Cache;

class InstanceActorController extends Controller
{
	public function profile()
	{
		$res = Cache::rememberForever(InstanceActor::PROFILE_KEY, function() {
			$res = (new InstanceActor())->first()->getActor();
			return json_encode($res, JSON_UNESCAPED_SLASHES);
		});
		return response($res)->header('Content-Type', 'application/activity+json');
	}

	public function inbox(Request $request)
	{
		if (!config('federation.activitypub.relay.enabled', false)) {
			return response('', 404);
		}

		$headers = $request->headers->all();
		$payload = $request->getContent();

		if (!$payload || empty($payload)) {
			return response('', 400);
		}

        Log::info('Received relay activity', ['headers' => $headers, 'payload' => $payload]);

		$activity = json_decode($payload, true, 8);
		if (!isset($activity['type'], $activity['actor'])) {
			return response('', 400);
		}

        $type = $activity['type'];
		$relayService = new RelayService();
        $relay = $relayService->verifyIncomingRelayActivity($headers, $payload);

        // If we couldn't verify the relay, return 401
        if ($relay === null) {
            if (in_array($type, ['Delete']) && is_string($activity['object'] ?? null)) {
                // Instances (apparently, at least mastodon.social) send Delete activities to the instance actor
                // Since DeleteWorker performs a signature check, we can safely forward the activity there
                dispatch(new DeleteWorker($headers, $payload))->onQueue('inbox');
                return response('', 202);
            } else {
                return response('', 401);
            }
        }

        // If we couldn't process the activity, return 400
        if (! $relayService->processIncomingRelayActivity($activity, $relay)) {
            return response('', 400);
        }

		return response('', 202);
	}

	public function outbox()
	{
		$res = json_encode([
			"@context" => [
                "https://www.w3.org/ns/activitystreams",
                "https://w3id.org/security/v1",
                [
                    "manuallyApprovesFollowers" => "as:manuallyApprovesFollowers",
                    "toot" => "http://joinmastodon.org/ns#",
                    "featured" => [
                        "@id" => "toot:featured",
                        "@type" => "@id"
                    ],
                    "featuredTags" => [
                        "@id" => "toot:featuredTags",
                        "@type" => "@id"
                    ],
                    "alsoKnownAs" => [
                        "@id" => "as:alsoKnownAs",
                        "@type" => "@id"
                    ],
                    "movedTo" => [
                        "@id" => "as:movedTo",
                        "@type" => "@id"
                    ],
                    "schema" => "http://schema.org#",
                    "PropertyValue" => "schema:PropertyValue",
                    "value" => "schema:value",
                    "discoverable" => "toot:discoverable",
                    "Device" => "toot:Device",
                    "Ed25519Signature" => "toot:Ed25519Signature",
                    "Ed25519Key" => "toot:Ed25519Key",
                    "Curve25519Key" => "toot:Curve25519Key",
                    "EncryptedMessage" => "toot:EncryptedMessage",
                    "publicKeyBase64" => "toot:publicKeyBase64",
                    "deviceId" => "toot:deviceId",
                    "claim" => [
                        "@type" => "@id",
                        "@id" => "toot:claim"
                    ],
                    "fingerprintKey" => [
                        "@type" => "@id",
                        "@id" => "toot:fingerprintKey"
                    ],
                    "identityKey" => [
                        "@type" => "@id",
                        "@id" => "toot:identityKey"
                    ],
                    "devices" => [
                        "@type" => "@id",
                        "@id" => "toot:devices"
                    ],
                    "messageFranking" => "toot:messageFranking",
                    "messageType" => "toot:messageType",
                    "cipherText" => "toot:cipherText",
                    "suspended" => "toot:suspended"
                ]
            ],
			'id' => config('app.url') . '/i/actor/outbox',
			'type' => 'OrderedCollection',
			'totalItems' => 0,
			'first' => config('app.url') . '/i/actor/outbox?page=true',
			'last' =>  config('app.url') . '/i/actor/outbox?min_id=0page=true'
		], JSON_UNESCAPED_SLASHES);
		return response($res)->header('Content-Type', 'application/activity+json');
	}
}
