@props([
    'secondary' => false,
    'tertiary' => false,
    'grid' => '',
    'veryNarrow' => false,
])

<div @class([
    'ts',
    'very padded',
    'horizontally fitted',
    'attached',
    'fluid',
    'segment',
    'secondary' => $secondary,
    'tertiary' => $tertiary,
])>
    <div @class([
        'ts',
        'container',
        'very narrow' => $veryNarrow,
        "{$grid} grid" => $grid,
    ])>
        {{ $slot ?? '' }}
    </div>
</div>
