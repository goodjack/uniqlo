@include('hmall-products.partials.card-base', [
    'slot' => view('hmall-products.partials.card-extra-content', ['hmallProduct' => $hmallProduct]),
])
