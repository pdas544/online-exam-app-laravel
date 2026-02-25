@props(['actions' => []])

<div class="card mb-4">
    <div class="card-header bg-light">
        <h5 class="card-title mb-0">
            <i class="bi bi-lightning-charge me-2"></i>Quick Actions
        </h5>
    </div>
    <div class="card-body">
        <div class="row g-2">
            @foreach($actions as $action)
                <div class="col-md-{{ $action['width'] ?? 3 }}">
                    <a href="{{ $action['route'] }}"
                       class="btn btn-{{ $action['color'] ?? 'primary' }} w-100">
                        <i class="bi {{ $action['icon'] }} me-2"></i>
                        {{ $action['label'] }}
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</div>
