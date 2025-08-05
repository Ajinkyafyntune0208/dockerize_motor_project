<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use Illuminate\Http\Request;
use App\Models\PasswordPolicy;
use App\Http\Controllers\Controller;
use App\Models\PasswordHistory;
use App\Models\ResetPassword;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Http\Controllers\Plugins\PHPGangsta_GoogleAuthenticator;
use Illuminate\Support\Facades\Mail;
use App\Mail\GoogleAuthenticationEmail;
use PDF;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Mail\SendResetEmail;

class UserController extends Controller
{
    public function __construct()
    {
        session()->remove('is_configured');
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!auth()->user()->can('user.list')) {
            abort(403, 'Unauthorized action.');
        } 
        $users = User::whereNotIn( 'email', [ 'motor@fyntune.com', 'webservice@fyntune.com' ] )->orderby('created_at','DESC')->paginate();
        $roles = \Spatie\Permission\Models\Role::all();
        return view('users.index', compact('users', 'roles'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (!auth()->user()->can('user.create')) {
            abort(403, 'Unauthorized action.');
        }
        $roles = \Spatie\Permission\Models\Role::all();
        $policyData =  PasswordPolicy::select('label', 'key', 'value')->get()->pluck('value', 'key');
        $qrCode = $this->generateQrcode();
        return view('users.create', compact('roles','policyData','qrCode'));
        
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (!auth()->user()->can('user.create')) {
            abort(403, 'Unauthorized action.');
        }
        $passwordPolicy = $this->getPasswordPolicy();
        $date = $passwordPolicy['date'];
        unset($passwordPolicy['date']);

        $validateData = [
            'name' => 'required|string',
            'email' => 'required|email|unique:user,email',
            'password' => $passwordPolicy,
            'role' => 'required',
            'otpExpiresIn' => 'required|integer|between:3,60',
            'askOtp' => 'nullable|string',
        ];

        $validator = Validator::make($request->all(), $validateData);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        } else {
           
            try {
                include_once app_path() . '/Helpers/PersonalDataEncryptionHelper.php';
                $data = [
                    'name' => $request->name,
                    'email' => $request->email,
                    'password' => \Illuminate\Support\Facades\Hash::make($request->password),
                    'remeber_token' => $request->_token,
                    'confirm_otp' => $request->askOtp ?? 'off',
                    'otp_expires_in' => $request->otpExpiresIn,
                    'password_expire_at' => $date,
                    'totp_secret' => encryptPiData($request->secret),
                    'otp_type' => $request->authMethod,
                ];
                $data['confirm_otp'] = $data['confirm_otp'] === 'on' ? 1 : 0;
                $is_configured =  !empty(config('mail.from.address')) && !empty(config('mail.mailers.smtp.host')) && !empty(config('mail.mailers.smtp.password')) && !empty(config('mail.mailers.smtp.port')) && !empty(config('mail.mailers.smtp.username'));
                if ($is_configured === false && $data['otp_type'] == 'email_otp') {
                    session()->put('is_configured', 'SMTP configuration is incomplete');
                    return redirect()->back();
                } else {
                    session()->remove('is_configured');
                }
                $user = User::create($data);
                $user->assignRole($request['role']);
                if($request->authMethod == 'totp'){
                    $qrCode_generated = $this->generateQrcode($request->secret);
                    $qrcode =  $qrCode_generated['QrCode'];
                    // $pdf_file = $qrCode_generated['pdf'];
                    Mail::to($request->email)->send(new GoogleAuthenticationEmail($qrcode,$qrCode_generated['secret'],null,null,$qrCode_generated['url']));
                }
                
                return redirect()->route('admin.user.index')->with([
                    'status' => 'User Created Successfully ..!',
                    'class' => 'success',
                    'secret' => $request->secret
                ]);  
            } catch (\Exception $e) {
                return redirect()->back()->with([
                    'status' => 'Something wents wrong ' . $e->getMessage() . '...!',
                    'class' => 'danger',
                ]);
            }
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\Response
     */
    public function show(User $user)
    {
        if (!auth()->user()->can('user.show')) {
            abort(403, 'Unauthorized action.');
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\Response
     */
    public function edit(User $user)
    {
        if (!auth()->user()->can('user.edit')) {
            abort(403, 'Unauthorized action.');
        }
        if($user->email==='motor@fyntune.com'){
            abort(403, 'Unauthorized action.');
        }
        $roles = \Spatie\Permission\Models\Role::all();
        $policyData =  PasswordPolicy::select('label', 'key', 'value')->get()->pluck('value', 'key');
        return view('users.edit', compact('user', 'roles','policyData'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, User $user)
    {
        if (!auth()->user()->can('user.edit')) {
            abort(403, 'Unauthorized action.');
        }

        if (!empty($request->password)) {
            $passwordPolicy = $this->getPasswordPolicy();
            $dateOfExpiry = $passwordPolicy['date'];
            unset($passwordPolicy['date']);
        } else {
            $passwordPolicy[] = 'nullable';
        }

        $validateData = [
            'name' => 'required|string',
            'email' => 'required|email',
            'password' => $passwordPolicy,
            'role' => 'required',
            'otpExpiresIn' => 'required|integer|between:3,60',
            'askOtp' => 'nullable|string',
        ];
        $email_validation = user::select('email')->where('email',$request->email)->where('id','!=',$user->id)->first() ?? '';
        if($email_validation != ''){
            return redirect()->back()->with([
                'status' => 'This email id already taken.',
                'class' => 'danger',
            ]);
        }
        $validator = Validator::make($request->all(), $validateData);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator->errors())->withInput();
        } else {
            $validateData = $validator->validated();
            $validateData['confirm_otp'] = $validateData['askOtp'] ?? 'off';
            $validateData['confirm_otp'] = $validateData['confirm_otp'] === 'on' ? 1 : 0;
            $validateData['otp_expires_in'] = $validateData['otpExpiresIn'];
            try {
                if ($validateData['password']) {
                    $validateData['password'] = \Illuminate\Support\Facades\Hash::make($validateData['password']);
                    $validateData['password_expire_at'] = $dateOfExpiry;
                } else {
                    unset($validateData['password']);
                }
                unset($validateData['askOtp']);
                unset($validateData['otpExpiresIn']);
                $is_configured =  !empty(config('mail.from.address')) && !empty(config('mail.mailers.smtp.host')) && !empty(config('mail.mailers.smtp.password')) && !empty(config('mail.mailers.smtp.port')) && !empty(config('mail.mailers.smtp.username'));
                if ($is_configured === false && $validateData['otp_type'] == 'email_otp') {
                    session()->put('is_configured', 'SMTP configuration is incomplete');
                    return redirect()->back();
                } else {
                    session()->remove('is_configured');
                }
                //* It check weather the enter password is already exist or not in database.
                if (!$this->checkPasswordExistOrNOt($request, $user->id)) {
                    return redirect()->back()->with([
                        'status' => 'Please select a unique password to continue and it should not be your recent 3 password.',
                        'class' => 'danger',
                    ]);
                }

                //* If password not exist in password history table than add new password
                if (!empty($validateData['password'])) {
                    PasswordHistory::create([
                        'user_id' => $user->id,
                        'old_password' => $validateData['password'],
                    ]);
                }
                if($user->otp_type != 'totp' && $request->authMethod == 'totp'){
                    include_once app_path() . '/Helpers/PersonalDataEncryptionHelper.php';
                    $qrCode_generated = $this->generateQrcode();
                    $qrcode =  $qrCode_generated['QrCode'];
                    // $pdf_file = $qrCode_generated['pdf'];
                    Mail::to($request->email)->send(new GoogleAuthenticationEmail($qrcode,$qrCode_generated['secret'],null,null,$qrCode_generated['url']  ));
                    $validateData['otp_type'] = 'totp';
                    $validateData['totp_secret'] = encryptPiData($qrCode_generated['secret']);
                } else if($request->authMethod == 'None'){
                    $validateData['otp_type'] = 'None';
                    $validateData['totp_secret'] = null;
                }
                else if($request->authMethod == 'email_otp'){
                    $validateData['otp_type'] = 'email_otp';
                    $validateData['totp_secret'] = null;
                }
                if($validateData['confirm_otp'] == 0){
                    $validateData['otp_type'] = 'None';
                    $validateData['totp_secret'] = null;
                }
                $user->update($validateData);
                $user->syncRoles($validateData['role']);
                return redirect()->route('admin.user.index')->with([
                    'status' => 'User Updated Successfully ..!',
                    'class' => 'success',
                ]);
            } catch (\Exception $e) {
                return redirect()->back()->with([
                    'status' => 'Something wents wrong ' . $e->getMessage() . '...!',
                    'class' => 'danger',
                ]);
            }
        }
    }
    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\Response
     */
    public function destroy(User $user)
    {
        if (!auth()->user()->can('user.delete')) {
            abort(403, 'Unauthorized action.');
        }
        if($user->email==='motor@fyntune.com'){
            abort(403, 'Unauthorized action.');
        }
        try {
            $user->delete();
            return redirect()->route('admin.user.index')->with([
                'status' => 'User Deleted Successfully ..!',
                'class' => 'success',
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with([
                'status' => 'Something wents wrong ' . $e->getMessage() . '...!',
                'class' => 'danger',
            ]);
        }
    }

    public function updateResetPassword(Request $request, $id)
    {
        $passwordPolicy = $this->getPasswordPolicy();
        $dateOfExpiry = $passwordPolicy['date'];
        unset($passwordPolicy['date']);
        $validateData = [
            'password' => $passwordPolicy
        ];
        
        $validator = Validator::make($request->all(), $validateData);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator->errors())->withInput();
        } else {
            //* It check weather the enter password is already exist or not in database.
            if (!$this->checkPasswordExistOrNOt($request, $id)) {
                return redirect()->back()->with([
                    'status' => 'Please select a unique password to continue and it should not be your recent 3 password.',
                    'class' => 'danger',
                ]);
            }

            //* If password not exist in password history table than add new password
            $password = \Illuminate\Support\Facades\Hash::make($request->password);
            PasswordHistory::create([
                'user_id' => $id,
                'old_password' => $password ,
            ]);
            User::where('id', $id)->update([
                'password' => $password,
                'password_expire_at' => $dateOfExpiry
            ]);
            ResetPassword::where('user_id', $id)->update(['password_updated' => '1']);
            return redirect()->route('success',['token'=>$request->_token])->with([
                'status' => 'User Updated Successfully ..!',
                'class' => 'success',
            ]);
        }
    }

    public function success(){
        return view('reset_password.successfulMsg');
    }

    public function getPasswordPolicy()
    {
        $passvalidation = PasswordPolicy::select('label', 'key', 'value')->get()->pluck('value', 'key');
        $validate = [];
        $validate['date'] = (Carbon::now()->adddays($passvalidation['password.passExpireInDays'])->format('Y-m-d H:i:s'));
        $validate[] = 'min:' . $passvalidation['password.minLength'];
        $validate[] = $passvalidation['password.upperCaseLetter'] == 'Y' ? 'regex:/[A-Z]/' : '';
        $validate[] = $passvalidation['password.lowerCaseLetter'] == 'Y' ? 'regex:/[a-z]/' : '';
        $validate[] = $passvalidation['password.atLeastOneNumber'] == 'Y' ? 'regex:/[0-9]/' : '';
        $validate[] = $passvalidation['password.atLeastOneSymbol'] == 'Y' ? 'regex:/[@$!%*#?&]/' : '';
        $validate[] = 'required';
        $validate[] = 'string';
        $validate[] = 'confirmed';
        return $validate;
    }

    public function checkPasswordExistOrNOt($request, $user)
    {
        $pass = PasswordHistory::where('user_id', $user)->orderBy('id', 'DESC')->get();
        foreach ($pass as $data) {
            if (Hash::check($request->password, $data->old_password)) {
                return false;
            }
        }
        //* If Previously enter password count is more then 3 recent passwords then Delete old passwords.
        $old_pass = PasswordHistory::where('user_id', $user)->orderBy('id', 'DESC')->limit(3)->get()->pluck('id');
        PasswordHistory::where('user_id', $user)->whereNotIn('id', $old_pass)->delete();
        return true;
    }

    public function generateQrcode($secret = null)
    {
        $ga = new PHPGangsta_GoogleAuthenticator();
        if($secret == null){
            $secret = $ga->createSecret();
        }

        $name = ucfirst(config('constants.motorConstant.SMS_FOLDER'));
        if (!in_array(config('app.env'), [
            'live',
        ])) {
            $name .='-'.ucfirst(config('app.env'));
        }
        
        $data = "otpauth://totp/{$name}t?secret=".$secret;

        $qrCodeUrl = $ga->getQRCodeGoogleUrl('Motor Enhancement', $secret);
        $qrcode = base64_encode(QrCode::format('svg')->size(200)->errorCorrection('H')->generate($data));
        return ['QrCode'=> $qrcode, 'secret'=>$secret, 'url'=>$qrCodeUrl];
        // $pdf = PDF::loadView('auth.qrcodepdf', ['secret' => $secret, 'qrcode' => $qrcode]);
        // //$path = public_path().'/download_files/qrcode/';
        // $pdf->setPaper('A4', 'landscape');
        // $pdfContent  = $pdf->output();
        // $fileName =  'qrcode' . $secret . '.pdf';
        // $qrcodePath = 'app/public/download_files/qrCodePdf/' . $fileName;
        // $qrcodeDirectory = 'download_files/qrCodePdf/';
            
        // Storage::disk('public')->put($qrcodeDirectory . $fileName, $pdfContent);
        // $fullQrCodePath = storage_path($qrcodePath);
        // $pdf->save($fullQrCodePath);
        // $pdf_file = $fullQrCodePath;
        // return ['QrCode'=> $qrcode, 'secret'=>$secret,'pdf' => $pdf_file];
        
    }
    public function checkTotp(Request $request)
    {
        $validateData = [
            'user_email' => 'required|email',
            'otp' => 'required'
        ];

        $validator = Validator::make($request->all(), $validateData);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()]);
        } else {
            $user = User::where('email', $request->user_email)->first();
            if($user->totp_secret != NULL){
                        
                $ga = new PHPGangsta_GoogleAuthenticator();
                $secret = $user->totp_secret;
                
                $checkResult = $ga->verifyCode($secret, $request->otp, 2);
            } else {
                $checkResult = true;
            }
            if($checkResult){
                return response()->json([
                    'status' => 200,
                    'message' => 'TOTP matched'
                ], 200); 
            } else {
                return response()->json([
                    'status' => 500,
                    'return_data' => [],
                    'message' => 'Totp does not matched'
                ], 500); 
            }
        }
    }
    public function showEmailIdForm(Request $request)
    {
        $qrCode_generated = $this->generateQrcode();
        $qrcode =  $qrCode_generated['QrCode'];
        Mail::to($request->requested_email)->send(new GoogleAuthenticationEmail($qrcode,$qrCode_generated['secret'],null,null,$qrCode_generated['url']  ));
        return view('auth.askEmailForm');
        
    }
    public function resendQrCode(Request $request){
        $validateData = [
            'requested_email' => 'required|email'
        ];

        $validator = Validator::make($request->all(), $validateData);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        } else {     
            $new_email = $request->requested_email;
            $user = User::where('email', $new_email)->first();
            
            $qrCode_generated = $this->generateQrcode();
            $qrcode =  $qrCode_generated['QrCode'];
            // $pdf_file = $qrCode_generated['pdf'];
            Mail::to($new_email)->send(new GoogleAuthenticationEmail($qrcode,$qrCode_generated['secret'],null,null,$qrCode_generated['url']));
            include_once app_path() . '/Helpers/PersonalDataEncryptionHelper.php';
            User::where('email', $new_email)->update([
                'totp_secret' => encryptPiData($qrCode_generated['secret'])
            ]);
            return redirect()->intended('admin');
        }
    }

    public function sendEmail(Request $request)
    {
        $user = User::where('email', ($request->email))->first();

        if (empty($user)) {
            return response()->json(
                [
                    'status' => false,
                    'return_data' => [],
                    'message' => 'Invalid User',
                ],
                500
            );
        }

        $email =  base64_encode($user->email);
        $expiryMinutes = config('app.password_reset.expiry_minutes', 60);
        $validity = Carbon::now()->addMinutes($expiryMinutes)->timestamp;
        $token = $email . '|' . $validity;
        $token = base64_encode($token);
        $url = ENV('APP_URL') . ('/admin/reset_password') . '?' . http_build_query(['token' => $token]);

        ResetPassword::create([
            'user_id' => $user->id,
            'reset_link' => $token,
            'link_expire_at' => date('Y-m-d H:i:s', $validity),
            'password_updated' => false
        ]);

        \App\Models\MailLog::create([
            "email_id" => $user->email,
            "first_name" => $user->name,
            "mail_body" => $url,
        ]);

        Mail::to($user->email)->send(new SendResetEmail($url, [
            'expiry' => $expiryMinutes
        ]));

        return response()->json(
            [
                'status' => true,
                'return_data' => [],
                'message' => 'Reset Password Link Sent Succefully.',
            ],
            200
        );
    }

    public function updatePassword(Request $request)
    {
        $request->merge([
            'email' => strtolower($request->email),
        ]);

        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:user,email',
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed'
            ]
        ], [
            'password.confirmed' => 'The password and confirmation password do not match.'
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return back()->with('status', 'Email not found');
        }

        $resetPassword = ResetPassword::where([
            'user_id' => $user->id,
            'reset_link' => $request->linkToken,
        ])->latest()->first();

        if (empty($resetPassword)) {
            return back()->with('error', 'Something went wrong');
        }

        if (strtotime($resetPassword->link_expire_at) < time()) {
            return back()->with('error', 'Password Reset link expired');
        }

        if ($resetPassword->password_updated) {
            return back()->with('error', 'The password reset token has already been used.');
        }

        $resetPassword->update([
            'password_updated' => true
        ]);

        $user->password = Hash::make($request->password);
        $user->save();

        return redirect()->route('admin.login')->with('status', 'Password successfully updated.');
    }
}
