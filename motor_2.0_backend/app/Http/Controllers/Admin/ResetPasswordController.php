<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\PasswordPolicy;
use App\Models\ResetPassword;

class ResetPasswordController extends Controller
{


    public Function index($id){
        $policyData =  PasswordPolicy::select('label', 'key', 'value')->get()->pluck('value', 'key');
        $pageOptions = [
            'excludeSideBar' => true,
            'excludeNavbar' => true,
            'excludeFooter' => true,
        ];
        return view('reset_password.resetPasswordLoginPage',compact('id','policyData','pageOptions'));  
    }



    public function resetPassword(Request $request)
    {
        $id = ResetPassword::where('reset_link', $request->token)->value('user_id');
        $user = User::select('id', 'email')->where('id', $id)->first();
        $linkExpire = ResetPassword::where('user_id', $user->id)->orderBy('id', 'DESC')->limit(1)->first();
        $password_updated  = $linkExpire->password_updated;
        $email = $user->email;
        if ((!empty($linkExpire->link_expire_at) && $linkExpire->link_expire_at < now()) || $linkExpire->password_updated == '1' )  {
            return view('reset_password.linkExpire',compact('password_updated'));
        }
        $policyData =  PasswordPolicy::select('label', 'key', 'value')->get()->pluck('value', 'key');
        $pageOptions = [
            'excludeSideBar' => true,
            'excludeNavbar' => true,
            'excludeFooter' => true,
        ];
        return view('reset_password.resetPassword', compact('id', 'email', 'pageOptions','policyData'));
    }
}
