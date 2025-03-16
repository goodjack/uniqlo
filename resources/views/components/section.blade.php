@props([
    'title' => '',
    'subTitle' => null,
    'rightAction' => null,
    'secondary' => false,
    'tertiary' => false,
    'grid' => '',
    'veryNarrow' => false,
])

<div @class([
    'ts very padded horizontally fitted attached fluid',
    'secondary segment' => $secondary,
    'tertiary segment' => $tertiary,
    'segment' => !$secondary && !$tertiary,
])>
    <div @class([
        'ts',
        'very narrow' => $veryNarrow,
        'container',
        $grid ? "{$grid} grid" : null,
    ])>
        @if ($title)
            <h2 class="ts large dividing header">
                {{ $title }}
                @if ($subTitle)
                    <div class="inline sub header">{{ $subTitle }}</div>
                @endif
                @if ($rightAction)
                    <div class="right floated">
                        {!! $rightAction !!}
                    </div>
                @endif
            </h2>
            <div class="ts hidden divider"></div>
        @endif

        {{ $slot ?? '' }}
    </div>
</div>
