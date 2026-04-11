@props([
    'title' => '',
    'subTitle' => null,
    'secondary' => false,
    'tertiary' => false,
    'inverted' => false,
    'padded' => 'very',
    'grid' => '',
    'veryNarrow' => false,
])

<div @class([
    'ts',
    'padded' => $padded === 'normal',
    'very padded' => $padded === 'very',
    'horizontally fitted attached fluid',
    'secondary segment' => $secondary,
    'tertiary segment' => $tertiary,
    'segment' => !$secondary && !$tertiary,
    'inverted' => $inverted,
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
                @isset($rightAction)
                    <div class="right floated">
                        {{ $rightAction }}
                    </div>
                @endisset
            </h2>
            <div class="ts hidden divider"></div>
        @endif

        {{ $slot ?? '' }}
    </div>
</div>
