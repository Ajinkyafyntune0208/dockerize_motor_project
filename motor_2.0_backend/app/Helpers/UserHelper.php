<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use App\Models\User;

if (!function_exists('getUserId')) {
    /**
     * Get the user ID based on the request context.
     *
     * @param Request $request
     * @return int|null
     */
    function getUserId()
    {

        if (app()->runningInConsole()) {
            return (int)0;
        } elseif (Auth::check()) {
            return Auth::id();
        } else {

            $user = User::where('email','webservice@fyntune.com')->first();

            if (!empty($user) && $user->hasRole('webservice')) {
                return $user->id;
            }
        }

        return null;
    }
}
