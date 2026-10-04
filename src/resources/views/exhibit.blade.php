@extends('layouts.header')

@section('css')
<link rel="stylesheet" href="@versioned('css/exhibit.css')">
@endsection

@section('content')

<body>
    <form class="exhibit_form" action="/sell" method="post" enctype="multipart/form-data">
        @csrf
        <div class="exhibit-form_container">
            <div class="exhibit-form_title">
                <h2 class="exhibit-form_exhibit_logo">商品の出品</h2>
            </div>
            <!------------- 画像ファイル入力 ---------------------->
            <style>
                /* チェックボックスを非表示にする */
                input[type="file"] {
                    display: none;
                    /* ファイル入力を非表示にする */
                }

                /* ボタンの基本スタイル */
                .toggle_button {

                    padding: 5px 10px;
                    border: 2px solid red;
                    border-radius: 25px;
                    background-color: white;
                    color: red;
                    cursor: pointer;
                    text-align: center;
                    transition: background-color 0.3s, color 0.3s;
                    font-size: 12px;
                    margin-top: 10px;
                    margin-left: 17%;
                }

                /* ボタンがホバーされたときのスタイル */
                .toggle_button:hover {
                    background-color: rgba(255, 0, 0, 0.1);
                }
            </style>
            <div class="exhibit-form_user_picture_wrapper">
                <img class="exhibit-form_user_picture" id="imagePreview" src="" alt="選択した画像がここに表示されます。">
                <label for="imageInput" class="toggle_button">画像を選択する</label>
                <input class="exhibit-form_input_user_image" type="file" id="imageInput" name="item_image">
            </div>
            @if ($errors->has('item_image'))
            <div style="width:100%;display:flex;justify-content:flex-start;">
                <div style="width:60%;display:flex;justify-content:flex-start;color:red;">
                    {{$errors->first('item_image')}}
                </div>
            </div>
            @endif
            <!-------------画像ファイル表示スクリプト------------------>
            <script>
                document.getElementById('imageInput').addEventListener('change', function(event) {
                    const file = event.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const imgElement = document.getElementById('imagePreview');
                            imgElement.src = e.target.result; // 画像のソースを設定
                        };
                        reader.readAsDataURL(file); // 画像をデータURLとして読み込む
                    }
                });
            </script>
            <h3 class="exhibit-form_subheading">
                商品の詳細
            </h3>
            <div class="exhibit-form_input_title">
                <h3 class="exhibit-form_input_title_logo">カテゴリー</h3>
            </div>
            <!---------- チェックボックス処理 ----------------------->
            <style>
                /* チェックボックスを非表示にする */
                input[type="checkbox"] {
                    display: none;
                    /* チェックボックスを非表示にする */
                }

                /* ボタンの基本スタイル */
                .toggle_button_category {
                    display: inline-block;
                    margin: 1vh 1%;
                    padding: 2px 1.5%;
                    border: 2px solid red;
                    border-radius: 25px;
                    background-color: white;
                    color: red;
                    cursor: pointer;
                    text-align: center;
                    transition: background-color 0.3s, color 0.3s;
                    font-size: 12px;
                }

                /* チェックボックスがチェックされたときのスタイル */
                input[type="checkbox"]:checked+.toggle_button_category {
                    background-color: red;
                    /* オン時の背景色 */
                    color: white;
                    /* オン時の文字色 */
                    border: 2px solid red;
                    /* 枠線は赤 */
                }
            </style>

            <div class="exhibit-form_category_box">
                <!-- チェックボックスとラベル -->
                @foreach($categories as $category)
                <input type="checkbox" id="toggleCheckbox{{ $loop->iteration }}" name=" categories[] " value="{{ $category['category'] }}">
                <label for="toggleCheckbox{{ $loop->iteration }}" class="toggle_button_category">{{ $category['category'] }}</label>
                @endforeach
                <!--                <input type="checkbox" id="toggleCheckbox14">
                <label for="toggleCheckbox14" class="toggle_button_category">ベビー・キッズ</label>
            -->
            </div>
            @if ($errors->has('categories'))
            <div style="width:100%;display:flex;justify-content:flex-start;">
                <div style="width:60%;display:flex;justify-content:flex-start;color:red;">
                    {{$errors->first('categories')}}
                </div>
            </div>
            @endif

            <!---------------------------------------------------->

            <div class="exhibit-form_input_title">
                <h3 class="exhibit-form_input_title_logo">商品の状態</h3>
            </div>
            <select class="exhibit-form_select_item_condition" name="condition">
                <option>良好</option>
                <option>目立った汚れや傷なし</option>
                <option>やや汚れや傷あり</option>
                <option>状態が悪い</option>
            </select>
            @if ($errors->has('condition'))
            <div style="width:100%;display:flex;justify-content:flex-start;">
                <div style="width:60%;display:flex;justify-content:flex-start;color:red;">
                    {{$errors->first('condition')}}
                </div>
            </div>
            @endif
            <h3 class="exhibit-form_subheading">
                商品名と説明
            </h3>
            <div class="exhibit-form_input_title">
                <h3 class="exhibit-form_input_title_logo">商品名</h3>
            </div>
            <div class="exhibit-form_input_box ">
                <input class="exhibit-form_input_field" type="text" name="item_name" value="">
            </div>
            @if ($errors->has('item_name'))
            <div style="width:100%;display:flex;justify-content:flex-start;">
                <div style="width:60%;display:flex;justify-content:flex-start;color:red;">
                    {{$errors->first('item_name')}}
                </div>
            </div>
            @endif
            <div class="exhibit-form_input_title">
                <h3 class="exhibit-form_input_title_logo">ブランド名</h3>
            </div>
            <div class="exhibit-form_input_box ">
                <input class="exhibit-form_input_field" type="text" name="brand_name" value="">
            </div>
            @if ($errors->has('brand_name'))
            <div style="width:100%;display:flex;justify-content:flex-start;">
                <div style="width:60%;display:flex;justify-content:flex-start;color:red;">
                    {{$errors->first('brand_name')}}
                </div>
            </div>
            @endif
            <div class="exhibit-form_input_title">
                <h3 class="exhibit-form_input_title_logo">商品の説明</h3>
            </div>
            <div class="exhibit-form_input_box ">
                <textarea class="exhibit-form_input_text_field" type="text" name="description" row="6" column="25"></textarea>
            </div>
            @if ($errors->has('description'))
            <div style="width:100%;display:flex;justify-content:flex-start;">
                <div style="width:60%;display:flex;justify-content:flex-start;color:red;">
                    {{$errors->first('description')}}
                </div>
            </div>
            @endif
            <div class="exhibit-form_input_title">
                <h3 class="exhibit-form_input_title_logo">販売価格</h3>
            </div>
            <div class="exhibit-form_input_box ">
                <input class="exhibit-form_input_field" type="text" name="price" value="">
            </div>
            @if ($errors->has('price'))
            <div style="width:100%;display:flex;justify-content:flex-start;">
                <div style="width:60%;display:flex;justify-content:flex-start;color:red;">
                    {{$errors->first('price')}}
                </div>
            </div>
            @endif
            <div class="exhibit-form_button_box">
                <button class="exhibit-form_redirect_button">出品する</button>
            </div>
        </div>
    </form>
</body>
@endsection
