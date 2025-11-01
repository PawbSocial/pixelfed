<?php

namespace App\Http\Controllers\Admin;

use App\Models\Relay;
use App\Services\RelayService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

trait AdminRelayController
{
    protected $relayService;

    /**
     * Initialize the relay service
     */
    protected function initializeRelayService()
    {
        if (!$this->relayService) {
            $this->relayService = new RelayService();
        }
    }

    /**
     * Display a listing of relays
     */
    public function relays()
    {
        $relays = Relay::orderBy('id')->paginate(20);

        return view('admin.relay.index', compact('relays'));
    }

    /**
     * Show the form for creating a new relay
     */
    public function relayCreate()
    {
        return view('admin.relay.create');
    }

    /**
     * Store a newly created relay
     */
    public function relayStore(Request $request)
    {
        $request->validate([
            'inbox_url' => 'required|url|unique:relays,inbox_url',
            'name' => 'nullable|string|max:255',
        ]);

        try {
            $this->initializeRelayService();

            // Auto-follow is enabled by default, but can be disabled
            $autoFollow = $request->boolean('auto_follow', true);

            $relay = $this->relayService->addRelay(
                $request->input('inbox_url'),
                $request->input('name'),
                $autoFollow
            );

            return redirect()
                ->route('admin.relays')
                ->with('success', 'Relay added successfully!' . ($autoFollow ? ' Follow request sent.' : ''));
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['inbox_url' => $e->getMessage()]);
        }
    }

    /**
     * Display the specified relay
     */
    public function relayShow(Relay $relay)
    {
        $this->initializeRelayService();
        $testResults = $this->relayService->testRelay($relay);

        return view('admin.relay.show', compact('relay', 'testResults'));
    }

    /**
     * Show the form for editing the specified relay
     */
    public function relayEdit(Relay $relay)
    {
        return view('admin.relay.edit', compact('relay'));
    }

    /**
     * Update the specified relay
     */
    public function relayUpdate(Request $request, Relay $relay)
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
            'inbox_url' => ['required', 'url', Rule::unique('relays', 'inbox_url')->ignore($relay)],
            'actor_url' => 'nullable|url',
            'is_active' => 'boolean',
            'following' => 'boolean',
        ]);

        // Handle the actor URL - if empty, we'll derive it from inbox URL
        $actorUrl = $request->input('actor_url');
        if (empty($actorUrl) && $request->input('inbox_url') !== $relay->inbox_url) {
            // If inbox URL changed and actor URL is empty, derive it
            $this->initializeRelayService();
            $actorUrl = $this->relayService->deriveActorUrl($request->input('inbox_url'));
        }

        $updateData = $request->only(['name', 'inbox_url', 'is_active', 'following']);
        if ($actorUrl) {
            $updateData['actor_url'] = $actorUrl;
        }

        $relay->update($updateData);

        return redirect()
            ->route('admin.relay.show', $relay)
            ->with('success', 'Relay updated successfully!');
    }

    /**
     * Remove the specified relay
     */
    public function relayDestroy(Relay $relay)
    {
        try {
            $this->initializeRelayService();

            // Unfollow the relay first if we're following it
            if ($relay->following) {
                $this->relayService->unfollowRelay($relay);
            }

            $relay->delete();
            $this->relayService->clearCache();

            return redirect()
                ->route('admin.relays')
                ->with('success', 'Relay removed successfully!');
        } catch (\Exception $e) {
            return back()->with('error', 'Error removing relay: ' . $e->getMessage());
        }
    }

    /**
     * Get relay statistics for dashboard
     */
    public function relayApiStats()
    {
        $stats = [
            'total' => Relay::count(),
            'active' => Relay::where('is_active', true)->count(),
            'following' => Relay::where('following', true)->count(),
            'healthy' => Relay::get()->filter(fn($r) => $r->isHealthy())->count(),
        ];

        return response()->json($stats);
    }

    /**
     * Test a relay connection
     */
    public function relayApiTest(Relay $relay)
    {
        $this->initializeRelayService();
        $results = $this->relayService->testRelay($relay);

        return response()->json($results);
    }

    /**
     * Follow a relay
     */
    public function relayApiFollow(Relay $relay)
    {
        try {
            $this->initializeRelayService();
            $success = $this->relayService->followRelay($relay);

            if ($success) {
                return response()->json(['success' => true, 'message' => 'Successfully sent follow request to relay!']);
            } else {
                return response()->json(['success' => false, 'message' => 'Failed to follow relay. Check the logs for details.'], 500);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error following relay: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Unfollow a relay
     */
    public function relayApiUnfollow(Relay $relay)
    {
        try {
            $this->initializeRelayService();
            $success = $this->relayService->unfollowRelay($relay);

            if ($success) {
                return response()->json(['success' => true, 'message' => 'Successfully sent unfollow request to relay!']);
            } else {
                return response()->json(['success' => false, 'message' => 'Failed to unfollow relay. Check the logs for details.'], 500);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error unfollowing relay: ' . $e->getMessage()], 500);
        }
    }
}
