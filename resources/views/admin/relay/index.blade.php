@extends('admin.partial.template-full')

@section('section')
</div>
<div class="header bg-primary pb-2 mt-n4">
    <div class="container-fluid">
        <div class="header-body">
            <div class="row align-items-center py-4">
                <div class="col-lg-6 col-7">
                    <p class="display-1 text-white d-inline-block mb-0">Relays</p>
                    <p class="text-white mb-0">Manage ActivityPub relays for federation</p>
                </div>
                <div class="col-lg-6 col-5 text-right">
                    <a href="{{ route('admin.relay.create') }}" class="btn btn-neutral">
                        <i class="fas fa-plus"></i> Add Relay
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-xl-3 col-md-6">
            <div class="card card-stats">
                <div class="card-body">
                    <div class="row">
                        <div class="col">
                            <h5 class="card-title text-uppercase text-muted mb-0">Total Relays</h5>
                            <span class="h2 font-weight-bold mb-0">{{ $relays->total() }}</span>
                        </div>
                        <div class="col-auto">
                            <div class="icon icon-shape bg-gradient-info text-white rounded-circle shadow">
                                <i class="fas fa-share-alt"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card card-stats">
                <div class="card-body">
                    <div class="row">
                        <div class="col">
                            <h5 class="card-title text-uppercase text-muted mb-0">Active</h5>
                            <span class="h2 font-weight-bold mb-0">{{ $relays->where('is_active', true)->count() }}</span>
                        </div>
                        <div class="col-auto">
                            <div class="icon icon-shape bg-gradient-success text-white rounded-circle shadow">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card card-stats">
                <div class="card-body">
                    <div class="row">
                        <div class="col">
                            <h5 class="card-title text-uppercase text-muted mb-0">Following</h5>
                            <span class="h2 font-weight-bold mb-0">{{ $relays->where('following', true)->count() }}</span>
                        </div>
                        <div class="col-auto">
                            <div class="icon icon-shape bg-gradient-warning text-white rounded-circle shadow">
                                <i class="fas fa-user-plus"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card card-stats">
                <div class="card-body">
                    <div class="row">
                        <div class="col">
                            <h5 class="card-title text-uppercase text-muted mb-0">Healthy</h5>
                            <span class="h2 font-weight-bold mb-0">{{ $relays->filter(fn($r) => $r->isHealthy())->count() }}</span>
                        </div>
                        <div class="col-auto">
                            <div class="icon icon-shape bg-gradient-primary text-white rounded-circle shadow">
                                <i class="fas fa-heartbeat"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success" role="alert">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger" role="alert">
            {{ session('error') }}
        </div>
    @endif

    <div class="row">
        <div class="col">
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0">Relay List</h3>
                </div>
                <div class="card-body">
                    @if($relays->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Domain</th>
                                        <th>Status</th>
                                        <th>Following</th>
                                        <th>Health</th>
                                        <th>Last Success</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($relays as $relay)
                                        <tr>
                                            <td>{{ $relay->id }}</td>
                                            <td>
                                                <a href="{{ route('admin.relay.show', $relay) }}" class="font-weight-bold">
                                                    {{ $relay->display_name }}
                                                </a>
                                            </td>
                                            <td>{{ parse_url($relay->inbox_url, PHP_URL_HOST) }}</td>
                                            <td>
                                                @if($relay->is_active)
                                                    <span class="badge badge-success">Active</span>
                                                @else
                                                    <span class="badge badge-secondary">Inactive</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($relay->following)
                                                    <span class="badge badge-primary">Following</span>
                                                @else
                                                    <span class="badge badge-light">Not Following</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($relay->isHealthy())
                                                    <span class="text-success">
                                                        <i class="fas fa-check"></i> Healthy
                                                    </span>
                                                @else
                                                    <span class="text-danger">
                                                        <i class="fas fa-times"></i> Unhealthy
                                                    </span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($relay->last_successful_delivery_at)
                                                    {{ $relay->last_successful_delivery_at->diffForHumans() }}
                                                @else
                                                    Never
                                                @endif
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="{{ route('admin.relay.show', $relay) }}" class="btn btn-outline-primary btn-sm">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="{{ route('admin.relay.edit', $relay) }}" class="btn btn-outline-secondary btn-sm">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <form method="POST" action="{{ route('admin.relay.destroy', $relay) }}" style="display: inline-block;">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Are you sure?')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-center">
                            {{ $relays->links() }}
                        </div>
                    @else
                        <div class="text-center py-4">
                            <p class="mb-0">No relays configured.</p>
                            <a href="{{ route('admin.relay.create') }}" class="btn btn-primary mt-2">
                                Add your first relay
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
