@props(['items' => [], 'title' => 'Recent Activity'])

<div class="card">
    <div class="card-header bg-light">
        <h5 class="card-title mb-0">
            <i class="bi bi-clock-history me-2"></i>{{ $title }}
        </h5>
    </div>
    <div class="card-body p-0">
        @if(count($items) > 0)
            <div class="list-group list-group-flush">
                @foreach($items as $item)
                    <div class="list-group-item">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1">{{ $item['title'] }}</h6>
                            <small class="text-muted">{{ $item['time'] }}</small>
                        </div>
                        <p class="mb-1 text-muted small">{{ $item['description'] }}</p>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-4 text-muted">
                <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                <span>No recent activity</span>
            </div>
        @endif
    </div>
</div>
