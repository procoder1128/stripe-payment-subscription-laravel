<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Mail\BasicMail;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Stripe\Stripe;
use Illuminate\Support\Carbon;

class ApplicationController extends Controller
{
    public function __construct()
    {
        // $this->middleware('auth');
    }
    
    public function index()
    {
        return view('pages.application.index');
    }

    public function paymentForm()
    {
        $stripe_key = config('app.stripe_publish_key');
        return view('pages.application.payment_form', compact('stripe_key'));
    }

    public function memberRegister(Request $request)
    {
        try {
            
            if(!auth()->check()) {
                $register_data = $request->all();
                $validator = Validator::make($register_data, [
                    'gender'        => 'required',
                    'employment'    => 'required',
                    'age'           => 'required',
                    'name'          => 'required',
                    'email'         => ['required', 'email', 'max:255', 'unique:users'],
                    'password'      => ['required', 'string', 'min:8'],
                    'repassword'    => 'required|same:password',
                ]);

                if ($validator->fails()) {
                    return back()->withInput()->withErrors($validator);
                } else {
                    // User Registration
                    $user = User::create([
                        'gender'        => $request->gender,
                        'employment'    => $request->employment,
                        'age'           => $request->age,
                        'name'          => $request->name,
                        'email'         => $request->email,
                        'password'      => Hash::make($request->password),
                        'role'          => 0,
                    ]);
    
                    $member_kind  = $request->member_kind;
                    $campaign     = $request->first_come_campaign ? true : false;
                    $process_kind = $request->input('kind');
                    
                    if($process_kind) {
                        $this->memberPayment($request, $user->id);
                    }
                    
                    // Membership Type and Campaign Registration
                    \App\Models\User::where('id', $user->id)->update([
                        'role'     => $member_kind,
                        'campaign' => $campaign
                    ]);

                    // Speical User Registration Mail
                    if($member_kind == '2') {
                        $this->specialUserMail($user);
                    }

                    // Campaign Registration Mail
                    if($campaign) {
                        $this->campaignMail($user);
                    }
                }
            } else {
                // Membership Type Update
                $member_kind  = $request->member_kind;
                $campaign     = $request->first_come_campaign ? true : false;
                $process_kind = $request->input('kind');

                if($process_kind) {
                    $this->memberPayment($request, auth()->user()->id);
                }

                \App\Models\User::where('id', auth()->user()->id)->update([
                    'role'     => $member_kind,
                    'campaign' => $campaign
                ]);

                // Speical User Registration Mail
                if($member_kind == '2') {
                    $this->specialUserMail(auth()->user());
                }

                // Campaign Registration Mail
                if($campaign) {
                    $this->campaignMail(auth()->user());
                }
            }

            return response()->json(['code' => 'success'], 200);
            
        } catch (\Throwable $err) {
            throw $err;
        }
    }

    public function paymentResult()
    {
        return view('pages.application.result');
    }

    public function memberPayment($request, $user_id)
    {
        // Stripe Payment Setting

        $stripe = null;
        $stripeKey = config('app.stripe_secret_key');
        \Stripe\Stripe::setApiKey($stripeKey);

        $token = $request->input('token');
        $process_kind = $request->input('kind');
        try {
            $stripeId = null;
            if(Auth::user()){
                $user = User::where('id', Auth::user()->id)->first();
                $stripeId = $user->offline_cashier_stripe_id;
            }
            if (!$stripeId || $process_kind != "saved") {
                $customer = \Stripe\Customer::create([
                    'description' => "Customer for ".$request->email." in kiiu",
                    'email' => $request->email,
                    'source' => $token['id'],
                ]);

                $stripeId = $customer->id;
                $exp_year = $token['card']['exp_year'];
                $exp_month = intval($token['card']['exp_month']);
                if($exp_month < 10)
                    $exp_month = "0".$exp_month;
                    $last4 = $token['card']['last4'];
                    $card_exp_date = $exp_year."-".$exp_month."-30";

                User::where('id', $user_id)->update([
                    'offline_cashier_stripe_id' => $stripeId,
                    'offline_cashier_card_brand' => $token['card']['brand'],
                    'offline_cashier_trial_ends_at' => $card_exp_date,
                    'offline_cashier_card_last_four' => $last4,
                ]);      

                // $stripe = Stripe\Charge::create([
                //     'amount' => $amount,
                //     'currency' => 'JPY',
                //     'description' => 'payment invoice for ' . $user->email. " in styleboard",
                //     'customer' => $stripeId,
                // ]);


                // $subscription = \Stripe\Subscription::create([
                //     'customer' => $stripeId,
                //     'items' => [[
                //         'price_data' => [
                //             'unit_amount' => 5000,
                //             'product' => [
                //                 'name' => '1 month subscription'
                //             ],
                //             'currency' => 'USD',
                //             'recurring' => [
                //                 'interval' => 'month',
                //             ],
                //         ],
                //     ]],
                // ]);

                $plan = \Stripe\Plan::create(array(
                    "product" => [
                        "name" => '1 month subscription'
                    ],
                    "amount" => 5000,
                    "currency" => 'JPY',
                    "interval" => 'month',
                    "interval_count" => 1
                ));

                $subscription = \Stripe\Subscription::create(array(
                    "customer" => $stripeId,
                    "items" => array(
                        array(
                            "plan" => $plan->id,
                        ),
                    ),
                ));

            }else{
                $customer = \Stripe\Customer::retrieve($stripeId);
                // $subscription = Stripe\Subscription::create([
                //     'customer' => $stripeId,
                //     'items' => [[
                //         'price_data' => [
                //             'unit_amount' => 5000,
                //             'currency' => 'USD',
                //             'product' => [
                //                 'name' => '1 month subscription'
                //             ],
                //             'recurring' => [
                //                 'interval' => 'month',
                //             ],
                //         ],
                //     ]],
                // ]);

                $plan = \Stripe\Plan::create(array(
                    "product" => [
                        "name" => '1 month subscription'
                    ],
                    "amount" => 5000,
                    "currency" => 'JPY',
                    "interval" => 'month',
                    "interval_count" => 1
                ));

                $subscription = \Stripe\Subscription::create(array(
                    "customer" => $stripeId,
                    "items" => array(
                        array(
                            "plan" => $plan->id,
                        ),
                    ),
                ));
            }
            
            $application = new \App\Models\Payment();
            $application->user_id = $user_id;
            $application->subscription_id = $subscription->id;
            $application->status = 0;
            $application->payment_date = Carbon::now();
            $application->save();

        } catch (\Exception $e) {
            $res["code"] = 'error';
            $res["msg"] = $e->getMessage();
        }
    }

    public function specialUserMail($data)
    {
        try {
            
            $subject = "【じぶんコンサル】特別会員にお申し込みいただき、ありがとうございました。";
            $message_content = $data->name."様\n";
            $message_content.= "この度は、特別会員にお申し込みいただき誠にありがとうございました。\n";
            $message_content.= "マイページを中心に、".$data->name."様の実生活に役立つ情報やサービスを適宜お届けしてまいります。\n\n";
            $message_content.= "https://kiiu.co.jp/mypage";
            $message_content.= "\n\n";
            $message_content.= "これからも、".$data->name."様が「自分を100%生きる」ために\n";
            $message_content.= "サポートいたしますので、引き続きよろしくお願いいたします。\n\n\n\n";

            // $auto_mail = new BasicMail($message, $subject);
            // Mail::to($data->email)->send($auto_mail);

            Mail::send('admin.mail.auto_mail', ['data'=> $message_content], function ($message) use ($data, $subject){
                $message->to($data->email,$data->name);
                $message->subject($subject);
            });

        } catch (\Throwable $err) {
            throw $err;
        }
    }

    public function campaignMail($data)
    {
        try {
            
            $subject = "【じぶんコンサル】モニターアンケートにご回答いただき、ありがとうございました。";
            $message_content = $data->name."様\n";
            $message_content.= "この度は、「じぶんコンサル」のモニターアンケートにご回答いただき誠にありがとうございました。\n";
            $message_content.= $data->name."様のご回答内容を拝見のうえ、サービス改善や体験談のサイト掲載を進めさせていただきます。\n\n\n";
            $message_content.= "なお体験談を掲載させていただく場合は、改めてご連絡を差し上げます。\n";
            $message_content.= "※体験談掲載が無い場合もございます。\n";
            $message_content.= "\n\n\n";
            $message_content.= "これからも、".$data->name."様が「自分を100%生きる」ために\n";
            $message_content.= "サポートいたしますので、引き続きよろしくお願いいたします。\n\n\n\n";

            // $auto_mail = new BasicMail($message, $subject);
            // Mail::to($data->email)->send($auto_mail);

            Mail::send('admin.mail.auto_mail', ['data'=> $message_content], function ($message) use ($data, $subject){
                $message->to($data->email,$data->name);
                $message->subject($subject);
            });

        } catch (\Throwable $err) {
            throw $err;
        }
    }
}
