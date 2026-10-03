@inject('hmallProductPresenter', 'App\Presenters\HmallProductPresenter')

{{-- 不吐價格（理由見 FavoriteController::cards），只留「現在特價」這類狀態標籤 --}}
@foreach ($hmallProducts as $hmallProduct)
    @include('hmall-products.item', [
        'hmallProduct' => $hmallProduct,
        'itemAttributes' =>
            'data-favorite-key="' . e($hmallProduct->brand . ':' . $hmallProduct->product_code) . '"' .
            ' data-on-offer="' . ($hmallProductPresenter->isOnOffer($hmallProduct) ? '1' : '0') . '"',
        'slot' => view('favorites.item-labels', ['hmallProduct' => $hmallProduct]),
        'actions' => view('favorites.item-remove-button', ['hmallProduct' => $hmallProduct]),
    ])
@endforeach
