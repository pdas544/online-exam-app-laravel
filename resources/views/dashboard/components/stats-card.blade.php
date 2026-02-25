@props([
    'title' => '',
    'value' => 0,
    'icon' => 'bi-pie-chart',
    'color' => 'primary',
    'trend' => null,
    'trendValue' => null
])

<div class="col-md-3 mb-4">
    <div class="card bg-{{ $color }} text-white h-100">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="card-title text-white-50">{{ $title }}</h6>
                    <h2 class="card-text mb-0">{{ $value }}</h2>
                    @if($trend)
                        <small class="text-white-50">
                            <i class="bi bi-arrow-{{ $trend == 'up' ? 'up' : 'down' }}"></i>
                            {{ $trendValue }} from last month
                        </small>
                    @endif
                </div>
                <i class="bi {{ $icon }} fs-1 opacity-50"></i>
            </div>
        </div>
    </div>
</div>
