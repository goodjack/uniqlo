@include('hmall-products.partials.card-base', [
    'cardAttributes' => $cardAttributes ?? '',
    'slot' => view('hmall-products.partials.card-extra-content', ['hmallProduct' => $hmallProduct]),
])
