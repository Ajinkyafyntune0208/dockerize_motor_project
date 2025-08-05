<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\UserTrail;

class TrackUserActivity
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        if (Auth::check()) {
            $data = [
                'user_id' => Auth::id(),
                'session_id' => session()->getId(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
            ];
            if ($request->isMethod('get')) {
                $data['parameters'] = json_encode($request->query());
            } elseif ($request->isMethod('post')) {
                $data['parameters'] = json_encode($request->except(['password', '_token']));
            }
            UserTrail::create($data);
        }
        return $response;
    }
}