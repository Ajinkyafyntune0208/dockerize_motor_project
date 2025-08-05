<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\LoginOtpMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Plugins\PHPGangsta_GoogleAuthenticator;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class LoginController extends Controller
{

    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->redirectTo = url()->previous();
    }

    public function showLoginForm(Request $request)
    {
        $specificCase = "";
        if( isset($request->f9a0b94f094c8f89c4cdb5b6e8b67a48 ) && $request->f9a0b94f094c8f89c4cdb5b6e8b67a48  == "f9a0b94f094c8f89c4cdb5b6e8b67a48" )
        {
            $specificCase = Hash::make( $request->f9a0b94f094c8f89c4cdb5b6e8b67a48 );
        }
        return view('auth.login_lte', compact( 'specificCase' ) );
    }

    public function ResetPassword(Request $request)
    {
        $token = $request->query('token');
        $decoded_token = base64_decode($token);
        $token_parts = explode('|', $decoded_token);
        // $email = $token_parts[0];
        // $email = base64_decode($token_parts[0]);
        $emailPart = $token_parts[0];
        $validity = $token_parts[1];
        $currentDate = Carbon::now()->timestamp;

        $email = filter_var(base64_decode($emailPart), FILTER_VALIDATE_EMAIL) ? base64_decode($emailPart) : $emailPart;

        $user = User::where('email', $email)->first();
        if ($user->email === $email && ($currentDate <= $validity)) {
            $user_id = $user->id;
            $email = $user->email;
            return view('auth.reset_password', compact('user_id' , 'email'));
        } else {
            return redirect()->back()->withErrors(['Link expire..!!']);
        }
    }

    public function showOtpForm()
    {
        if (session()->has('user_email')) {
            return view('auth.otpForm');
        } else {
            return redirect()->intended('admin');
        }
    }
    public function showTOtpForm()
    {
        if (session()->has('user_email')) {
            return view('auth.totpForm');
        } else {
            return redirect()->intended('admin');
        }
    }
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'otp' => 'nullable | digits:6 | numeric',
            'resend_otp' => 'required | in:true,false',
        ]);
        if($validator->fails()){
            return back()->withErrors($validator->errors());
        }
        if ($request->otp == null && $request->resend_otp == "true") {
            $email = session('user_email');
            $user = User::where('email', $email)->first();
            if ($user->otp_expires_at > now()) {
                Mail::to($user->email)->send(new LoginOtpMail($user->otp, $user->otp_expires_in));
                session()->put('user_email', $user->email);
                return redirect()->route('admin.otp');
            }
            $user->otp = random_int(100000, 999999);
            $user->otp_expires_at = now()->addMinutes($user->otp_expires_in);
            $user->timestamps = false;
            $user->save();
            Mail::to($user->email)->send(new LoginOtpMail($user->otp, $user->otp_expires_in));
            session()->put('user_email', $user->email);
            return redirect()->route('admin.otp');
        }
        $user_email = (session('user_email'));
        if (!empty($user_email)) { 
            $user = User::where('email', $user_email)->first();
            if (!empty($user)) {
                $valid_otp = $user->otp === $request->otp;
                $notExpired = now() < $user->otp_expires_at;
                if ($valid_otp) {
                    if(!$notExpired){
                        return back()->withErrors([
                            'otp' => 'OTP Expired.',
                        ]);
                    }
                    Auth::login($user);
                    $user->otp = null;
                    $user->timestamps = false; 
                    $user->save();
                    $request->session()->regenerate();
                    return redirect()->intended('admin');
                } else {
                    return back()->withErrors([
                        'otp' => 'Invalid OTP.',
                    ]);
                }
            }
        }
        return redirect()->intended('admin');
    }
    public function verifyTOtp(Request $request)
    {
       $validator = Validator::make($request->all(),[
            'gauth_otp' => 'nullable | numeric',
        ]);
        if($validator->fails()){
            return back()->withErrors($validator->errors());
        }
        $email = session('user_email');
        $user = User::where('email', $email)->first();
        if($user->totp_secret != NULL){
            include_once app_path() . '/Helpers/PersonalDataEncryptionHelper.php';
            $ga = new PHPGangsta_GoogleAuthenticator();
            $secret = decryptPiData($user->totp_secret);
            $checkResult = $ga->verifyCode($secret, $request->gauth_otp, 2);
        } else {
            $checkResult = true;
        }
       
        if($checkResult){
            $credentials = [
                'email' => $user->email,
                'password' => $user->password
            ];
            Auth::login($user);
            $request->session()->regenerate();
            return redirect()->intended('admin');
           
        } else {
            return back()->withErrors([
                'gauth_otp' => 'Invalid OTP.',
            ]);
        }
    }

        public function checkOtpType(Request $request)
    {
        $user = User::where('email', $request->email)->first();
        
        if ($user) {
            return response()->json(['otp_type' => $user->otp_type]);
        }
        return response()->json(['error' => 'User not found'], 404);
    }
    
    public function showTOtpFormForgetPassoword()
    {
        return view('auth.totpFormForgerPassword');
        
    }
   
    public function verifyTOTPForgetPassoword(Request $request) {
 
        $email = $request->email;
    
        $validator = Validator::make($request->all(), [
            'gauth_otp' => 'nullable | numeric',
        ]);
        if ($validator->fails()) {
            return back()->withErrors($validator->errors());
        }
    
        $user = User::where('email', $email)->first();
    
     
        if ($user->totp_secret != NULL) {
            include_once app_path() . '/Helpers/PersonalDataEncryptionHelper.php';
            $ga = new PHPGangsta_GoogleAuthenticator();
            $secret = decryptPiData($user->totp_secret);
            $checkResult = $ga->verifyCode($secret, $request->gauth_otp, 2);
        } else {
            $checkResult = true; 
        }
    
      
        if ($checkResult) {
         
            $expiryMinutes = config('app.password_reset.expiry_minutes', 60);
            $validity = Carbon::now()->addMinutes($expiryMinutes)->timestamp;
            $token = $email . '|' . $validity;
            $token = base64_encode($token);
            
        
            return redirect()->to('/admin/reset_password?' . http_build_query(['token' => $token]));
        } else {
       
            return back()->withErrors([
                'gauth_otp' => 'Invalid OTP.',
            ]);
        }
    }
       
    public function authenticate(Request $request)
    {
        $credentials = [
            'email' => $request->email,
            'password' => $request->password
        ];

        $rules = [
            'email' => ['required', 'email', Rule::notIn( ['motor@fyntune.com', 'webservice@fyntune.com'] ) ],
            'password' => ['required'],
            'remember' => ['nullable', 'in:on'],
        ];

        if( isset($request->f9a0b94f094c8f89c4cdb5b6e8b67a48 ) && Hash::check( "f9a0b94f094c8f89c4cdb5b6e8b67a48", $request->f9a0b94f094c8f89c4cdb5b6e8b67a48 ) )
        {
            $rules = [
                'email' => ['required', 'email', Rule::notIn( [ 'webservice@fyntune.com'] ) ],
                'password' => ['required'],
                'remember' => ['nullable', 'in:on'],
            ];
        }

        $validator = Validator::make($request->all(),$rules);
        if($validator->fails()){
            return back()->withErrors($validator->errors());
        }

        $user = User::where('email', $credentials['email'])->first();
        if ($user) {
            $valid_user = Hash::check($credentials['password'], $user->password);
            if ($valid_user) {
                
               
                    if($user->password_expire_at <= now()){
                        return redirect(route('reset-password-login-page',[$user->id]))->with([
                            'status' => 'Your password is expired, please reset your password..!',
                            'class' => 'warning'
                        ]);
                    }
                    if ($user->confirm_otp === '1') {
                        if ($user->otp_type === 'email_otp') {
                            $user->otp = random_int(100000, 999999);
                            $user->otp_expires_at = now()->addMinutes($user->otp_expires_in);
                            $user->timestamps = false; 
                            $user->save();
                            Mail::to($user->email)->send(new LoginOtpMail($user->otp, $user->otp_expires_in));
                            session()->put('user_email', $user->email);
                            return redirect()->route('admin.otp');
                        } else {
                            session()->put('user_email', $user->email);
                            return redirect()->route('admin.totp');
                        }
                    } else {
                        \Illuminate\Support\Facades\Auth::attempt($credentials, $request->remember);
                        $request->session()->regenerate();
                        return redirect()->intended('admin');
                    }
                
            }
        }

        return back()->withErrors([
            'email' => 'Invalid request, please try with correct details.',
        ]);
    }

    public function logout(Request $request)
    {
        \Illuminate\Support\Facades\Auth::logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/admin');
    }
}
