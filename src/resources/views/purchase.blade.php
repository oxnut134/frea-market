@extends('layouts.header')

@section('css')
<link rel="stylesheet" href="@versioned('css/purchase.css')">
@endsection

@section('content')

<body>
    @if($canceled)
    <div class="purchase-form_notice">決済をキャンセルしました。</div>
    @endif
    @if ($errors->has('checkout'))
    <div class="purchase-form_notice purchase-form_notice--error">{{ $errors->first('checkout') }}</div>
    @endif

    <form class="purchase-form" action="/purchase/{{ $item['id'] }}/checkout" method="post">
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
                @if($own_pending)
                <div class="purchase-form_shipping_address">{{ $own_pending->payment_method_label }}</div>
                @else
                <select class="purchase-form_select_payment_method" name="payment_method" id="payment-method-select">
                    <option value="" disabled @if(!old('payment_method')) selected @endif>選択してください</option>
                    @foreach($payment_methods as $value => $label)
                    <option value="{{ $value }}" @if(old('payment_method') === $value) selected @endif>{{ $label }}</option>
                    @endforeach
                </select>
                @endif
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

                    @if(!$own_pending)
                    <a class="purchase-form_shipping_address_redirect_button" href="/purchase/address/{{ $item['id'] }}">変更する</a>
                    @endif
                </div>
                @if($own_pending)
                <div class="purchase-form_shipping_address">{{ $own_pending->delivery_address }}</div>
                @else
                <div class="purchase-form_shipping_address">{{ '〒'.$post_code }}</div>
                <div class="purchase-form_shipping_address">{{ $address.' '.$building }}</div>
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
                    <span class="purchase-form_purchase_payment_method"><span id="selected-payment-method">{{ $own_pending ? $own_pending->payment_method_label : ($payment_methods[old('payment_method')] ?? '') }}</span></span>
                </div>
                @if($own_pending && $own_pending->stripe_checkout_url)
                {{-- 自分が確保中で、決済がまだ終わっていない --}}
                <div class="purchase-form_pending_message">購入手続きの途中です。</div>
                <a class="purchase-form_purchase_button purchase-form_purchase_button--link" href="{{ $own_pending->stripe_checkout_url }}">決済を続ける</a>
                @elseif($own_pending)
                {{-- コンビニ払いの支払い番号を発行済み --}}
                <div class="purchase-form_pending_message">コンビニでのお支払いをお待ちしています。支払い方法は Stripe からのメールをご確認ください。</div>
                @else
                <button class="purchase-form_purchase_button">
                    購入する
                </button>
                @endif
            </div>
        </div>
        </form>
</body>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const paymentMethodSelect = document.getElementById('payment-method-select');
        const selectedPaymentMethod = document.getElementById('selected-payment-method');
        if (!paymentMethodSelect) {
            return;
        }

        // 選んだ支払い方法の表示名を、小計にも表示する
        paymentMethodSelect.addEventListener('change', function () {
            selectedPaymentMethod.textContent = paymentMethodSelect.options[paymentMethodSelect.selectedIndex].text;
        });
    });
</script>
@endsection
