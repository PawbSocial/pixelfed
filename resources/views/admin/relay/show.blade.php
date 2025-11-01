@extends('admin.partial.template')

@section('section')
<div class="title">
    <h3 class="font-weight-bold">Relay Details</h3>
    <p class="lead">View and manage relay: {{ $relay->display_name }}</p>
</div>
<hr>

<div class="row">
    <div class="col-12 col-md-8">
        <div class="card shadow-none border">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-share-alt mr-2"></i>
                    {{ $relay->display_name }}
                </h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-3"><strong>ID:</strong></div>
                    <div class="col-md-9">{{ $relay->id }}</div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-3"><strong>Name:</strong></div>
                    <div class="col-md-9">{{ $relay->name ?: 'N/A' }}</div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-3"><strong>Inbox URL:</strong></div>
                    <div class="col-md-9">
                        <a href="{{ $relay->inbox_url }}" target="_blank" class="text-decoration-none">
                            {{ $relay->inbox_url }}
                            <i class="fas fa-external-link-alt ml-1 text-muted" style="font-size: 0.8em;"></i>
                        </a>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-3"><strong>Actor URL:</strong></div>
                    <div class="col-md-9">
                        @if($relay->actor_url)
                            <a href="{{ $relay->actor_url }}" target="_blank" class="text-decoration-none">
                                {{ $relay->actor_url }}
                                <i class="fas fa-external-link-alt ml-1 text-muted" style="font-size: 0.8em;"></i>
                            </a>
                        @else
                            <span class="text-muted">N/A</span>
                        @endif
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-3"><strong>Status:</strong></div>
                    <div class="col-md-9">
                        @if($relay->is_active)
                            <span class="badge badge-success">Active</span>
                        @else
                            <span class="badge badge-secondary">Inactive</span>
                        @endif

                        @if($relay->following)
                            <span class="badge badge-primary ml-2">Following</span>
                        @else
                            <span class="badge badge-outline-secondary ml-2">Not Following</span>
                        @endif
                    </div>
                </div>

                @if($relay->metadata)
                    <div class="row mb-3">
                        <div class="col-md-3"><strong>Software:</strong></div>
                        <div class="col-md-9">
                            {{ $relay->metadata['software'] ?? 'Unknown' }}
                        </div>
                    </div>

                    @if(isset($relay->metadata['summary']))
                        <div class="row mb-3">
                            <div class="col-md-3"><strong>Description:</strong></div>
                            <div class="col-md-9">{{ $relay->metadata['summary'] }}</div>
                        </div>
                    @endif
                @endif

                <div class="row mb-3">
                    <div class="col-md-3"><strong>Health:</strong></div>
                    <div class="col-md-9">
                        @if($relay->failed_delivery_count > 0)
                            <span class="text-warning">
                                {{ $relay->failed_delivery_count }} failed deliveries
                            </span>
                            @if($relay->last_failed_delivery_at)
                                <small class="text-muted d-block">
                                    Last failed: {{ $relay->last_failed_delivery_at->diffForHumans() }}
                                </small>
                            @endif
                        @else
                            <span class="text-success">Healthy</span>
                        @endif

                        @if($relay->last_successful_delivery_at)
                            <small class="text-muted d-block">
                                Last successful: {{ $relay->last_successful_delivery_at->diffForHumans() }}
                            </small>
                        @endif
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-3"><strong>Created:</strong></div>
                    <div class="col-md-9">{{ $relay->created_at->format('M j, Y \a\t g:i A') }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-md-4">
        <div class="card shadow-none border">
            <div class="card-header">
                <h6 class="mb-0">Actions</h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="{{ route('admin.relay.edit', $relay) }}" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-edit mr-1"></i> Edit
                    </a>

                    @if($relay->following)
                        <button class="btn btn-outline-warning btn-sm" onclick="unfollowRelay({{ $relay->id }})">
                            <i class="fas fa-user-times mr-1"></i> Unfollow
                        </button>
                    @else
                        <button class="btn btn-outline-success btn-sm" onclick="followRelay({{ $relay->id }})">
                            <i class="fas fa-user-plus mr-1"></i> Follow
                        </button>
                    @endif

                    <button class="btn btn-outline-info btn-sm" onclick="testRelay({{ $relay->id }})">
                        <i class="fas fa-vial mr-1"></i> Test Connection
                    </button>

                    <hr>

                    <button class="btn btn-outline-danger btn-sm" onclick="confirmDelete({{ $relay->id }})">
                        <i class="fas fa-trash mr-1"></i> Delete
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Back button -->
<div class="mt-4">
    <a href="{{ route('admin.relays') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left mr-1"></i> Back to Relays
    </a>
</div>
@endsection

@section('script')
<script>
function followRelay(relayId) {
    if (confirm('Follow this relay?')) {
        axios.post(`/i/admin/api/relay/${relayId}/follow`)
            .then(response => {
                location.reload();
            })
            .catch(error => {
                alert('Failed to follow relay: ' + (error.response?.data?.message || error.message));
            });
    }
}

function unfollowRelay(relayId) {
    if (confirm('Unfollow this relay?')) {
        axios.post(`/i/admin/api/relay/${relayId}/unfollow`)
            .then(response => {
                location.reload();
            })
            .catch(error => {
                alert('Failed to unfollow relay: ' + (error.response?.data?.message || error.message));
            });
    }
}

function testRelay(relayId) {
    axios.post(`/i/admin/api/relay/${relayId}/test`)
        .then(response => {
            if (response.data.reachable) {
                alert('✓ Relay is reachable and working properly!');
            } else {
                alert('✗ Relay test failed: ' + (response.data.error || 'Unknown error'));
            }
        })
        .catch(error => {
            alert('Test failed: ' + (error.response?.data?.message || error.message));
        });
}

function confirmDelete(relayId) {
    if (confirm('Are you sure you want to delete this relay? This action cannot be undone.')) {
        axios.delete(`/i/admin/relay/${relayId}`)
            .then(response => {
                window.location.href = '{{ route("admin.relays") }}';
            })
            .catch(error => {
                alert('Failed to delete relay: ' + (error.response?.data?.message || error.message));
            });
    }
}
</script>
@endsection
