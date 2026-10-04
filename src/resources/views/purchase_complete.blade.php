@extends('layouts.header')

@section('css')
<link rel="stylesheet" href="@versioned('css/purchase.css')">
@endsection

@section('content')

<body>
    {{-- 表示するだけの画面。購入の確定は Webhook で行う --}}
    <div class="purchase-complete">
        @if($purchase->status === \App\Models\Purchase::STATUS_PAID)
        <div class="purchase-complete_message">ご購入ありがとうございました。購入が完了しました。</div>
        @elseif($purchase->status === \App\Models\Purchase::STATUS_PENDING && $purchase->payment_method === \App\Models\Purchase::PAYMENT_METHOD_KONBINI)
        <div class="purchase-complete_message">コンビニでのお支払いをお待ちしています。</div>
        <div class="purchase-complete_detail">支払い方法は Stripe からのメールをご確認ください。</div>
        @elseif($purchase->status === \App\Models\Purchase::STATUS_PENDING)
        <div class="purchase-complete_message">決済を確認しています。</div>
        <div class="purchase-complete_detail">しばらくしてから再読み込みしてください。マイページの「購入した商品」でも確認できます。</div>
        @else
        <div class="purchase-complete_message">購入は完了していません。</div>
        <div class="purchase-complete_detail">お手数ですが、もう一度購入手続きをお願いします。</div>
        @endif
        <div class="purchase-complete_detail">{{ $item->item_name }}　{{ '¥ '.number_format($purchase->amount) }}（{{ $purchase->payment_method_label }}）</div>
        <div class="purchase-complete_links">
            <a href="/mypage/?tab=buy">購入した商品を見る</a>
            <a href="/">商品一覧へ</a>
        </div>
    </div>
</body>
@endsection
