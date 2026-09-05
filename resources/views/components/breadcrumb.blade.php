<!-- Breadcrumb Navigation Component -->
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        @foreach($items as $index => $item)
            @if($index === count($items) - 1)
                <!-- Last item (active) -->
                <li class="breadcrumb-item active">{{ $item['label'] }}</li>
            @else
                <!-- Clickable items -->
                <li class="breadcrumb-item">
                    @if(isset($item['route']))
                        <a href="{{ $item['route'] }}">{{ $item['label'] }}</a>
                    @else
                        {{ $item['label'] }}
                    @endif
                </li>
            @endif
        @endforeach
    </ol>
</nav>
