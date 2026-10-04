@extends('layouts.header')

@section('css')
<link rel="stylesheet" href="@versioned('css/purchase.css')">
@endsection

@section('content')

<body>
    @if(isset($errors))
    @endif

    <form class="purchase-form" action="/stripe" method="post">
        @csrf
        <div class="purchase-form_confirm_box">
            <div class="purchase-form_item_image_wrapper">
                <img class="purchase-form_item_image" src="{{ $item->image_url }}">
                <div class="purchase-form_item_information">
                    <div class="purchase-form_item_name">{{ $item['item_name'] }}</div>
                    <div class="purchase-form_item_price">{{ ' ¥ '.number_format($item['price']) }}</div>
                </div>
            </div>
            <div class="purchase-form_item_payment_method">
                <h3 class="purchase-form_item_payment_method_column_name">支払い方法</h3>
<select class="purchase-form_select_payment_method" name="payment_method" id="payment-method-select">
<!--     <select class="purchase-form_select_payment_method" name="payment_method">-->
                    <option disabled selected>選択してください</option>
                    <option value="コンビニ払い">コンビニ払い</option>
                    <option value="カード支払い">カード支払い</option>
                </select>
                @if ($errors->has('payment_method'))
                <div style="width:100%;display:flex;justify-content:center;">
                    <div style="width:85%;display:flex;justify-content:flex-start;color:red;">
                        {{$errors->first('payment_method')}}
                    </div>
                </div>
                @endif
            </div>
            <div class="purchase-form_shipping_address_wrapper">
                <div class="purchase-form_shipping_address_redirect_row">
                    <h3 class="purchase-form_shipping_address_column_name">配送先</h3>

                    <a class="purchase-form_shipping_address_redirect_button" href="/purchase/address/{{ $item['id'] }}">変更する</a>
                </div>
                <div class="purchase-form_shipping_address">{{ '〒'.$post_code }}</div>
                <div class="purchase-form_shipping_address">{{ $address.' '.$building }}</div>
                @if ($errors->has('delivery_address'))
                <div style="width:100%;display:flex;justify-content:center;">
                    <div style="width:85%;display:flex;justify-content:flex-start;color:red;">
                        {{$errors->first('delivery_address')}}
                    </div>
                </div>
                @endif
            </div>
        </div>
        <div class="purchase-form_purchase_box">
            <div class="purchase-form_purchase_bag">

                <div class="purchase-form_purchase_payment_wrapper">
                    <span class="purchase-form_purchase_payment">商品代金</span>
                    <span class="purchase-form_purchase_payment">{{ ' ¥ '.number_format($item['price']) }}</span>
                </div>
                <div class="purchase-form_purchase_payment_method_wrapper">
                    <span class="purchase-form_purchase_payment_method">支払い方法</span>
                    @if($payment_method)
                    <span class="purchase-form_purchase_payment_method"><span id="selected-payment-method"></span></span>
                    @endif
                </div>
                <!------- payment_method はセット済------->
                    <input type="hidden" name="delivery_address" value="{{ $post_code.$address.$building}}">
                    <input type="hidden" name="price" value="{{ $item['price'] }}">
                    <input type="hidden" name="item_name" value="{{ $item['item_name'] }}">
                    <input type="hidden" name="item_id" value="{{ $item['id'] }}">
                    <input type="hidden" name="email" value="{{ $email }}">
                    <button class="purchase-form_purchase_button">
                        購入する
                    </button>
            </div>
            <!--<div id="item-id" data-id="{{ $item['price'] }}"></div>-->
        </div>
        </form>
</body>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const paymentMethodSelect = document.getElementById('payment-method-select');
        const selectedPaymentMethod = document.getElementById('selected-payment-method');

        paymentMethodSelect.addEventListener('change', function () {
            const selectedValue = paymentMethodSelect.value;
            selectedPaymentMethod.textContent = selectedValue;
        });
    });
</script>
@endsection
