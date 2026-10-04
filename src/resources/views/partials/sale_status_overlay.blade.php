{{-- 商品画像に重ねる販売状況（販売中の商品には何も重ねない）。sale-status_frame の中に置く --}}
@if($item->sale_status !== \App\Models\Item::SALE_STATUS_ON_SALE)
@if(!empty($awaiting_payment) && $item->sale_status === \App\Models\Item::SALE_STATUS_TRADING)
<span class="sale-status_overlay sale-status_overlay--awaiting">お支払い待ち</span>
@else
<span class="sale-status_overlay sale-status_overlay--{{ $item->sale_status }}">{{ $item->sale_status_label }}</span>
@endif
@endif
