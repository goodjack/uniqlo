@props(['title', 'subTitle' => null, 'rightAction' => null])

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
