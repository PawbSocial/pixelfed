@extends('admin.partial.template-full')

@section('section')
</div>
<div class="header bg-primary pb-2 mt-n4">
    <div class="container-fluid">
        <div class="header-body">
            <div class="row align-items-center py-4">
                <div class="col-lg-6 col-7">
                    <p class="display-1 text-white d-inline-block mb-0">Add Relay</p>
                    <p class="text-white mb-0">Add a new ActivityPub relay</p>
                </div>
                <div class="col-lg-6 col-5 text-right">
                    <a href="{{ route('admin.relays') }}" class="btn btn-neutral">
                        <i class="fas fa-arrow-left"></i> Back to Relays
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid mt-4">
    <div class="row justify-content-center">
        <div class="col-xl-8 col-lg-10">
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0">Relay Information</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.relay.store') }}">
                        @csrf

                        <div class="form-group">
                            <label for="inbox_url" class="form-control-label">Inbox URL <span class="text-danger">*</span></label>
                            <input type="url"
                                   id="inbox_url"
                                   name="inbox_url"
                                   class="form-control @error('inbox_url') is-invalid @enderror"
                                   value="{{ old('inbox_url') }}"
                                   placeholder="https://relay.example.com/inbox"
                                   required>
                            <small class="form-text text-muted">
                                The inbox URL of the relay (e.g., https://relay.example.com/inbox)
                            </small>
                            @error('inbox_url')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="name" class="form-control-label">Name (Optional)</label>
                            <input type="text"
                                   id="name"
                                   name="name"
                                   class="form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name') }}"
                                   placeholder="Example Relay">
                            <small class="form-text text-muted">
                                A friendly name for this relay. If left empty, the domain name will be used.
                            </small>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        @if(config('federation.activitypub.relay.auto_follow', true))
                            <div class="form-group">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox"
                                           id="auto_follow"
                                           name="auto_follow"
                                           class="custom-control-input"
                                           value="1"
                                           {{ old('auto_follow', true) ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="auto_follow">
                                        Automatically follow this relay
                                    </label>
                                </div>
                                <small class="form-text text-muted">
                                    If enabled, a follow request will be sent to the relay immediately after adding it.
                                </small>
                            </div>
                        @endif

                        <div class="form-group mb-0">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add Relay
                            </button>
                            <a href="{{ route('admin.relays') }}" class="btn btn-outline-secondary">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Help Card -->
            <div class="card mt-4">
                <div class="card-header">
                    <h3 class="mb-0">
                        <i class="fas fa-info-circle"></i> About Relays
                    </h3>
                </div>
                <div class="card-body">
                    <h5>What are ActivityPub Relays?</h5>
                    <p>
                        Relays are special ActivityPub servers that help distribute public content across the fediverse.
                        When you follow a relay, your server will send public posts to the relay, which then distributes
                        them to other servers that also follow the relay.
                    </p>

                    <h5>AodeRelay Compatibility</h5>
                    <p>
                        This implementation is specifically designed to work with AodeRelay and other compatible relay software.
                        The typical relay inbox URL format is <code>https://relay.domain.com/inbox</code>.
                    </p>

                    <h5>Popular Public Relays</h5>
                    <ul>
                        <li><code>https://relay.fedi.agency/inbox</code> - General purpose relay</li>
                        <li><code>https://noagendasocial.com/relay</code> - No Agenda Social relay</li>
                        <li><code>https://relay.mas.to/inbox</code> - Mastodon relay</li>
                    </ul>

                    <div class="alert alert-warning">
                        <strong>Note:</strong> Always verify that you trust a relay before adding it, as it will receive
                        all your public content and may redistribute it to other servers.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
