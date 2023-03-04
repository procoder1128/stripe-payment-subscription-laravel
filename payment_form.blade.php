@extends('layouts.app')

@section('title')
{{__('決済_申し込み|')}}
@endsection

@section('content')
    <!-- Start Header -->
    @include('layouts.header')
    <!-- End -->

    <!-- Body content -->
    <section class="sect_payment">
        <form action="" method="post">
            <div class="s-inner">
                <div class="sect_title">簡単お申し込みフォーム</div>
                <div class="register_panel @if(auth()->user()) disabled @endif">
                    <div class="title">無料会員登録されていない方<br>は、以下をご入力ください。</div>
                    <table border="0" cellpadding="10" width="100%">
                        <tr>
                            <td width="40%" class="label">性別</td>
                            <td class="controls">
                                <label><input type="radio" checked value="1" name="gender" id="gender">男性</label>
                                <label><input type="radio" value="2" name="gender" id="gender">女性</label>
                            </td>
                        </tr>
                        <tr>
                            <td class="label">就労有無</td>
                            <td class="controls">
                                <label><input type="radio" checked value="1" name="employment" id="employment">働いている</label>
                                <label><input type="radio" value="2" name="employment" id="employment">働いていない</label>
                            </td>
                        </tr>
                        <tr>
                            <td class="label">年齢</td>
                            <td class="controls">
                                <input type="number" id="age" name="age">
                            </td>
                        </tr>
                        <tr>
								<td class="label">お名前<br>(ペンネーム)</td>
                            <td class="controls">
                                <input type="text" id="name" name="name">
                            </td>
                        </tr>
                        <tr>
								<td class="label">メール<br>アドレス
									</td>
                            <td class="controls">
                                <input type="email" id="email" name="email">
                            </td>
                        </tr>
                        <tr>
                            <td class="label">パスワード</td>
                            <td class="controls">
                                <input type="password" id="password" name="password">
                            </td>
                        </tr>
                        <tr>
                            <td class="label">パスワード（確認）</td>
                            <td class="controls">
                                <input type="password" id="repassword">
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="payment_panel">
                    <div class="step">
                        <div class="title">
                            <span class="number">1</span>会員種類をお選びください。
                        </div>
                        <div class="controls">
                            <label>
                                <input type="radio" id="member_kind" name="member_kind" value="1">
                                通常会員
                            </label>
                            <label>
                                <input type="radio" id="member_kind" name="member_kind" value="2">
                                特別会員
                            </label>
                        </div>
                        <div class="desc">※いつでも変更は可能です。</div>
                    </div>

                    <div class="step">
                        <div class="title">
                            <span class="number">2</span>キャンペーンお申込み有無をお選びください。
                        </div>
                        <div class="controls">
                            <label class="mb-1">
                                <input type="checkbox" id="first_come_campaign" name="first_come_campaign" value="1" checked>
                                先着キャンペーンに申し込む
                            </label>
                            <label>
                                <input type="checkbox" id="special_campaign" name="special_campaign" value="1" onchange="change_special_campaign();" disabled>
                                特別キャンペーンに申し込む
                            </label>
                        </div>
                        <div class="code_panel">
                            <label>コード</label>
                            <input type="text" class="txt_code" id="txt_code" disabled>
                        </div>

                        <div class="desc">※特別キャンペーンは現在行っておりません。コードの記入も不要です。</div>
                    </div>

                    <div class="step">
                        <div class="title">
                            <span class="number">3</span>クレジットカードでお支払いください。
                        </div>
                        <div class="controls stripe">
                            <?php if(auth()->user() && auth()->user()->offline_cashier_stripe_id){ ?>
                            <div class="radio">
                                <label>
                                <input class="form-check-input" type="radio" id="savedPayment" name="optradio" value='saved_card'>
                                    <?php echo sprintf("過去に使用したカード選択（%s, カード番号:xxxx xxxx xxxx %s, 有効期限:%s）", auth()->user() && auth()->user()->offline_cashier_card_brand, Auth::user()->offline_cashier_card_last_four, Auth::user()->offline_cashier_trial_ends_at) ?>
                                </label>
                            </div>
                            <?php } ?>
                            <label>
                                <input class="form-check-input" type="radio" id="cardPayment" name="optradio" value='card'>
                                新しいカードを使用する
                            </label>
                        </div>
                        <div id="card-part" <?php if(auth()->user() && auth()->user()->offline_cashier_stripe_id){ ?> style="display: none;" <?php } ?>>
                            <div id="card-element">
                            </div>
                            <div id="card-errors" style="color: #dc3545;"></div>
                        </div>
                        <div class="credit_catds">
                            <img src="{{asset('assets/img/stripe/visa.png')}}">
                            <img src="{{asset('assets/img/stripe/mastercard.png')}}">
                            <img src="{{asset('assets/img/stripe/american.png')}}">
                            <img src="{{asset('assets/img/stripe/jcb.png')}}">
                        </div>
                    </div>
                </div>

                <div class="agree_part">
                    <input type="checkbox" name="agree" id="privacy_agree">
                    <a href="{{route('user.policy')}}" target="_blank">個人情報保護方針に同意する。</a>
                </div>

                @if(auth()->check())
                <button type="button" class="btn-red" id="onChoosePaymentMethod" onclick="check_payment();">お申し込みを完了する</button>
                @else
                <button type="button" class="btn-red"  id="onChoosePaymentMethod" onclick="check_payment();">お申し込みを完了する</button>
                @endif
                <a class="btn-blue-border left-icon white" href="{{route('/')}}">サイトトップへ戻る<i class="fa fa-chevron-left" aria-hidden="true"></i></a>
            </div>
        </form>
    </section>
    <!-- End -->
@endsection

@section('script')
<script src="https://js.stripe.com/v3/"></script>
<script>
    var page_type = 'payment';
    var payment_url = "{{route('user.application.membership')}}";
    var login_status = "{{auth()->check()}}";

    var stripe = Stripe('{{ $stripe_key }}',{
        locale: 'ja'
    });

    var elements = stripe.elements();
    var style = {
        base: {
            color: '#32325d',
            lineHeight: '18px',
            fontFamily: '"Helvetica Neue", Helvetica, sans-serif',
            fontSmoothing: 'antialiased',
            fontSize: '16px',
            '::placeholder': {
                color: '#aab7c4'
            }
        },
        invalid: {
            color: '#fa755a',
            iconColor: '#fa755a'
        }
    };
    var card = elements.create('card', { style: style });
    // // Add an instance of the card Element into the `card-element` <div>.
    if($('#card-element').length)
        card.mount('#card-element');

    try {
        Stripe.setPublishableKey('{{ $stripe_key }}');
    } catch {}

    card.on('change',function(){
        $('#card-errors').html('')
    })

    $('input[name=optradio]').change(function () {
        console.log('#### change');
        var val = $(this).val();
        val == 'card' ?  $('#card-part').show() : $('#card-part').hide()
    })

    function check_payment(){
        if(check_form()){
            var type = $('input[name=optradio]:checked').val();
            if (type == 'card'){
                stripe.createToken(card).then(function (result) {
                    if (result.error) {
                        $('#card-errors').html(result.error.message)
                    } else {
                        stripeResponseHandler(result.token, 'new');
                    }
                })
            } else if (type == 'saved_card') {
                stripeResponseHandler('', 'saved');
            }
        }
    }

    function stripeResponseHandler(token, kind_val='new') {
        var dataVal = [];
        if(login_status == '') {
            dataVal = {
                token,
                kind:kind_val,
                gender: $('#gender').val(),
                employment: $('#employment').val(),
                age: $('#age').val(),
                name: $('#name').val(),
                email: $('#email').val(),
                password: $('#password').val(),
                first_come_campaign: $('#first_come_campaign').val(),
                member_kind: $('#member_kind').val(),
                _token:'{{csrf_token()}}',
            }
        } else {
            dataVal = {
                token,
                kind:kind_val,
                first_come_campaign: $('#first_come_campaign').val(),
                member_kind: $('#member_kind').val(),
                _token:'{{csrf_token()}}'
            }
        }

        $.ajax({
            url : payment_url, 
            type : "POST",
            data : dataVal,
            dataType: "json",
            beforeSend: function(xhr){
                $('#onChoosePaymentMethod').hide();
                $('#overlay').show();
            },
            error: function(xhr,status,error){
                $('#overlay').hide();
                $('#onChoosePaymentMethod').show();
                toastr["error"]('stripe決済でエラーが発生しました。', 'エラー', {
                    positionClass: 'toast-top-right',
                    closeButton: true,
                    progressBar: true,
                    newestOnTop: true,
                    rtl: $("body").attr("dir") === "rtl" || $("html").attr("dir") === "rtl",
                    timeOut: 5000
                });
            },
            success: function(result){
                $('#overlay').hide();
                $('#onChoosePaymentMethod').show();
                if(result.code == 'success'){
                    toastr["success"](result.msg, '通知', {
                        positionClass: 'toast-top-right',
                        closeButton: true,
                        progressBar: true,
                        newestOnTop: true,
                        rtl: $("body").attr("dir") === "rtl" || $("html").attr("dir") === "rtl",
                        timeOut: 5000
                    });
                     window.location.href="/payment/result"
                }else{
                    toastr["error"](result.msg, 'エラー', {
                        positionClass: 'toast-top-right',
                        closeButton: true,
                        progressBar: true,
                        newestOnTop: true,
                        rtl: $("body").attr("dir") === "rtl" || $("html").attr("dir") === "rtl",
                        timeOut: 5000
                    });
                }
            }
        });       
    }

</script>
@endsection