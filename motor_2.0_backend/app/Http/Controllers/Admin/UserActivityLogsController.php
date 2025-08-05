<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\userActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class UserActivityLogsController extends Controller
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return parent::toArray($request);
    }

    public function index(Request $request)
    {
        $types = userActivityLog::distinct('service_type')->pluck('service_type')->toArray();

        $operations = userActivityLog::distinct('operation')->pluck('operation')->toArray();

        $users = [];
        if (auth()->user()->hasRole('Admin')) {
            $users = User::all()->toArray();
        }

        $activities = [];
        if ($request->has('type') && $request->has('from') && $request->has('to')) {
            $activities = userActivityLog::when(!auth()->user()->hasRole('Admin'), function ($query) {
                    return $query->where('user_id', auth()->user()->id);
                })
                ->when($request->has('users'), function ($query) use ($request) {
                    return $query->whereIn('user_id', $request->users);
                })
                ->when($request->has('commit_id') && !empty($request->commit_id), function ($query) use ($request) {
                    return $query->where('commit_id', $request->commit_id);
                })
                ->when($request->has('session_id') && !empty($request->session_id), function ($query) use ($request) {
                    return $query->where('session_id', $request->session_id);
                })->with('user:id,name');


            $activities = $activities->whereBetween('created_at', [
                Carbon::parse($request->from)->startOfDay(), Carbon::parse($request->to)->endOfDay(),
            ])
                ->whereIn('service_type', $request->type)
                ->whereIn('operation', $request->operation)
                ->orderBy('id', 'DESC')
                ->paginate($request->paginate);
        }

        return view('activityLogs.index', compact('activities', 'types', 'operations', 'users'));
    }

    public function show($id)
    {
        $pageOptions = [
            'excludeSideBar' => true,
            'excludeNavbar' => true,
            'excludeFooter' => true,
        ];
        $record = userActivityLog::find($id)->toArray();
        if(auth()->user()->hasRole('Admin') || ($record['user_id'] ?? 0) == auth()->user()->id) {
            return view('activityLogs.show', compact('pageOptions', 'record'));
        }
        return response('Unauthorized Access', 401);
    }
}
