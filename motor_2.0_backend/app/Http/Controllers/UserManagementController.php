<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\User;
use App\Mail\TalktoExpert;
use Illuminate\Http\Request;
use App\Models\UserProductJourney;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class UserManagementController extends Controller
{

    public function userLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'userName' => 'required',
            'password' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }
        try {
            $userName = $request->userName;
            $password = $request->password;
            // $user = User::where(['username' => $userName])->first();
            $user = DB::table('users')->where(['username' => $userName])->first();
            if (!$user) 
            {
                return response()->json(['status' => false, 'msg' => 'Invalid username'], 200);
            }
            else
            {
                if($user->password_status == 1)
                {
                    if(Hash::check($password, $user->password))
                    {
                        //UserLogin
                        $userLoginData = [
                            'user_id' => $user->user_id,
                            'timestamp'=> date("Y-m-d H:i:s"),
                            'ip_address'=> getClientIpEnv(),
                            'status'=> 'valid'
                        ];
                        // UserLogin::Insert($userLoginData);
                        DB::table('user_login')->Insert($userLoginData);
                        //UserLoginToken
                        $token = generateToken();
                        $currentTime = Carbon::now()->addMinutes(15);
                        $UserLoginTokenData = [
                            "user_id"       =>  $user->user_id,
                            "token"         =>  $token,
                            "token_expired" =>  $currentTime->format('Y-m-d H:i:s'),
                            "email_link"    =>  'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']
                        ];
                        // UserLoginToken::updateOrCreate(['user_id' => $user->user_id], $UserLoginTokenData);
                        DB::table('user_login_token')->updateOrInsert(['user_id' => $user->user_id], $UserLoginTokenData);

                        return response()->json([
                            'status' => true,
                            'msg' => 'Login Successfully',
                            'userId' => $user->user_id,
                            'corpId' => $user->user_type_id
                        ]);
                    }
                    else
                    {
                        // $userLogins = UserLogin::where(['user_id' => $user->user_id, 'status'=> 'invalid'])->get();
                        $userLogins = DB::table('user_login')->where(['user_id' => $user->user_id, 'status'=> 'invalid'])->get();

                        $loginAttempts = $userLogins->count();
                        if($loginAttempts >= 3)
                        {
                            return response()->json(['status' => false, 'msg' => 'Invalid Password'], 200);
                        }
                        else
                        {
                            $userLoginData = [
                                'user_id' => $user->user_id,
                                'timestamp'=> date("Y-m-d H:i:s"),
                                'ip_address'=> getClientIpEnv(),
                                'status'=> 'invalid'
                            ];
                            // UserLogin::Insert($userLoginData);
                            DB::table('user_login')->Insert($userLoginData);
                            //
                            $remainAttempts = 3 - $loginAttempts;
                            if($remainAttempts == 0)
                            {
                                 $msg = "Invalid Password and You have no attempts.";
                            
                            }
                            else
                            {
                                 $msg = "Invalid Password and You have remain ".$remainAttempts. " attempt(s) out of 3 allowed attempts.";
                            }
                            return response()->json(['status' => false, 'msg' => $msg], 200);
                        }
                    }
                }
                else
                {
                    return response()->json(['status' => false, 'msg' => 'Sorry! This user is inactive'], 200);
                }
            }
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        } 
    }

    public function createUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'firstName' => 'required|alpha',
            'lastName' => 'required|alpha',
            'email' => 'required|email:rfc',
            'gender' => 'required',
            'dob' => 'required|date',
            'age' => 'required|numeric',
            'mobileNo' => 'required|numeric|digits:10'
        ]);
        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }
        try {
            $validateData['api_token'] = generateToken();
            $validateData = snakeCase($validateData);

            $user = User::updateOrCreate(['email' => $validateData['email']], $validateData);
            
            MailLog::create([
                'user_journey_id' => $userJourney->id,
                'to' => config('constants.mail.lead_mail_address'),
                'mail_content' => $validateData,
                'subject' => 'Lead for Motor Insurance from '. config('app.name'),
            ]);

            $data = [
                'status' => true,
                'message' => 'User Created Successfully..!',
                'userId' => $user->user_id,
                'token' => $user->api_token,
                'user_journey_id' => $userJourney->journey_id
            ];
            return response()->json($data, 200);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function updateUser(Request $request, User $user)
    {
        $validator = Validator::make($request->all(), [
            'firstName' => 'required|alpha',
            'lastName' => 'required|alpha',
            'email' => 'required|email|unique:users,email,' . $user->user_id,
            'gender' => 'required',
            'dob' => 'required|date',
            'mobileNo' => 'required|numeric|digits:10'
        ]);
        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }
        try {
            $validateData = snakeCase($request->all());
            $user->update($validateData);
            $data = [
                'status' => true,
                'message' => 'User Updated Successfully..!',
            ];
            return response()->json($data, 200);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function callUs(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'contactNo' => 'required|digits:10|numeric'
        ]);
        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }
        if($request->enquiryId)
        {
            $UserProductJourneyData = UserProductJourney::where("user_product_journey_id", customDecrypt($request->enquiryId))->first();
            if(!empty($UserProductJourneyData))
            {
                $leadData = array(
                    'name' => $UserProductJourneyData->user_fname . '  ' . $UserProductJourneyData->user_lname,
                    'email' => $UserProductJourneyData->user_email,
                    'mobile_no' => $request->contactNo,
                    'subject' => 'Lead For Motor Insurance From Ola'
                );
            }
        }
        else
        {
            $leadData = array(
                'name' => 'NA',
                'email' => 'NA',
                'mobile_no' => $request->contactNo,
                'subject' => 'Lead For Motor Insurance From Ola'
            );
        }

        $mailSendTo = '';

        // Mail::to([$mailSendTo])->send(new TalktoExpert($leadData));

        $mailData = [
            'mail_sent_to' => $mailSendTo,
            'subject' => $leadData['subject'],
            'mail_body' => (new TalktoExpert($leadData))->render(),
            'user_product_journey_id' => isset($request->enquiryId) ? customDecrypt($request->enquiryId) : 0,
            'status' => 'Y',
        ];

        // MailLog::create($mailData);

        DB::table('mail_logs')->Insert($mailData);

        return response()->json(['status' => true, 'message' => 'Email sent successfully!']);
    }
}
