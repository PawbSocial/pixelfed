@extends('admin.partial.template')

@section('section')
<div class="title">
    <h3 class="font-weight-bold">Edit Relay</h3>
    <p class="lead">Modify relay settings</p>
</div>
<hr>

<div class="row">
    <div class="col-12 col-md-8">
        <div class="card shadow-none border">
            <div class="card-header">
                <h5 class="mb-0">Relay Configuration</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.relay.update', $relay) }}">
                    @csrf
                    @method('PUT')

                    <div class="form-group row">
                        <label for="name" class="col-md-3 col-form-label text-md-right">Name</label>
                        <div class="col-md-9">
                            <input type="text"
                                   class="form-control @error('name') is-invalid @enderror"
                                   id="name"
                                   name="name"
                                   value="{{ old('name', $relay->name) }}"
                                   placeholder="Optional display name">
                            @error('name')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                            <small class="form-text text-muted">
                                Optional display name for this relay
                            </small>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="inbox_url" class="col-md-3 col-form-label text-md-right">Inbox URL *</label>
                        <div class="col-md-9">
                            <input type="url"
                                   class="form-control @error('inbox_url') is-invalid @enderror"
                                   id="inbox_url"
                                   name="inbox_url"
                                   value="{{ old('inbox_url', $relay->inbox_url) }}"
                                   required>
                            @error('inbox_url')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                            <small class="form-text text-muted">
                                The inbox URL for the relay (e.g., https://relay.example.com/inbox)
                            </small>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="actor_url" class="col-md-3 col-form-label text-md-right">Actor URL</label>
                        <div class="col-md-9">
                            <input type="url"
                                   class="form-control @error('actor_url') is-invalid @enderror"
                                   id="actor_url"
                                   name="actor_url"
                                   value="{{ old('actor_url', $relay->actor_url) }}"
                                   placeholder="Auto-detected from inbox URL">
                            @error('actor_url')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                            <small class="form-text text-muted">
                                Optional: Actor URL will be auto-detected if left blank
                            </small>
                        </div>
                    </div>

                    <div class="form-group row">
                        <div class="col-md-9 offset-md-3">
                            <div class="form-check">
                                <input type="checkbox"
                                       class="form-check-input"
                                       id="is_active"
                                       name="is_active"
                                       value="1"
                                       {{ old('is_active', $relay->is_active) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_active">
                                    Active
                                </label>
                                <small class="form-text text-muted">
                                    Uncheck to temporarily disable this relay
                                </small>
                            </div>
                        </div>
                    </div>

                    <div class="form-group row">
                        <div class="col-md-9 offset-md-3">
                            <div class="form-check">
                                <input type="checkbox"
                                       class="form-check-input"
                                       id="following"
                                       name="following"
                                       value="1"
                                       {{ old('following', $relay->following) ? 'checked' : '' }}>
                                <label class="form-check-label" for="following">
                                    Following
                                </label>
                                <small class="form-text text-muted">
                                    Whether we're currently following this relay
                                </small>
                            </div>
                        </div>
                    </div>

                    <div class="form-group row">
                        <div class="col-md-9 offset-md-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save mr-1"></i> Update Relay
                            </button>
                            <a href="{{ route('admin.relay.show', $relay) }}" class="btn btn-secondary ml-2">
                                <i class="fas fa-times mr-1"></i> Cancel
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-md-4">
        <div class="card shadow-none border">
            <div class="card-header">
                <h6 class="mb-0">Current Status</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Status:</strong>
                    @if($relay->is_active)
                        <span class="badge badge-success">Active</span>
                    @else
                        <span class="badge badge-secondary">Inactive</span>
                    @endif
                </div>

                <div class="mb-3">
                    <strong>Following:</strong>
                    @if($relay->following)
                        <span class="badge badge-primary">Yes</span>
                    @else
                        <span class="badge badge-outline-secondary">No</span>
                    @endif
                </div>

                @if($relay->metadata)
                    <div class="mb-3">
                        <strong>Software:</strong>
                        {{ $relay->metadata['software'] ?? 'Unknown' }}
                    </div>
                @endif

                <div class="mb-3">
                    <strong>Health:</strong>
                    @if($relay->failed_delivery_count > 0)
                        <span class="text-warning">
                            {{ $relay->failed_delivery_count }} failures
                        </span>
                    @else
                        <span class="text-success">Healthy</span>
                    @endif
                </div>

                <hr>

                <div class="d-grid gap-2">
                    <button class="btn btn-outline-info btn-sm" onclick="testRelay({{ $relay->id }})">
                        <i class="fas fa-vial mr-1"></i> Test Connection
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
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
</script>
@endsection
