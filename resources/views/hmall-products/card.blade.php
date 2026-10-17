@include('hmall-products.partials.card-base', [
    'price' => view('hmall-products.partials.card-price', ['hmallProduct' => $hmallProduct]),
    'slot' => view('hmall-products.partials.card-labels', ['hmallProduct' => $hmallProduct]),
])
