{{--
    收起的清單後面那顆展開鈕。收起只在 JS 跑起來後才發生（商品頁的 setUpCollapsibles()），
    沒跑 JS 時清單維持全開、鈕維持 hidden。
--}}
<button type="button" class="ts basic fluid button uq-expand" data-collapse-on="{{ $on }}" hidden>{{ $label }}</button>
