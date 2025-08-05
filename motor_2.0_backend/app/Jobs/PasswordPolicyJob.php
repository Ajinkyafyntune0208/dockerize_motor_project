<?php

namespace App\Jobs;

use Carbon\Carbon;
use App\Models\User;
use App\Models\ResetPassword;
use Illuminate\Bus\Queueable;
use App\Mail\PasswordPolicyMail;
use App\Models\PasswordPolicy;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class PasswordPolicyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    public function handle()
    {
        info('Job is dispatched');
        $checkMailEnable = PasswordPolicy::select('key','value')->get()->pluck('value','key');
        if($checkMailEnable['password.policy.mail.enable'] == 'N'){
            return false;
        }
        info('Mail enable is run');
        $user = User::select('id', 'name', 'email', 'password_expire_at')->whereBetween('password_expire_at', [Carbon::now()->startOfDay(), Carbon::now()->addDay($checkMailEnable['password.notificationExpireInDays'] ?? 7)])->get();
        foreach ($user as $u) {
            $token = [$u->id, $u->email, now()];
            $token = checksum_encrypt($token);
            ResetPassword::create([
                'user_id' => $u->id,
                'reset_link' => $token,
                'link_expire_at' => Carbon::now()->addDay($checkMailEnable['password.linkExpireInDays'] ?? 3)->format('Y-m-d H:i:s'), //* Link expire in 3 days.
                'password_updated' => '0'
            ]);
            Mail::to($u->email)->send(new PasswordPolicyMail($u, $token));
            info('Reset password is created and mail is send');
        }
    }
}
