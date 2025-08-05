<?php

namespace App\Http\Controllers\Lte\Admin;

use App\Http\Controllers\Controller;
use App\Models\MotorServerErrors;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ServerErrorLogController extends Controller
{
    public function index(Request $request)
    {
        $data = [];
        if (!empty($request->query())) {
            $data = MotorServerErrors::whereBetween('created_at', [Carbon::parse($request->from)->startOfDay(), Carbon::parse($request->to)->endOfDay()])
            ->orderBy('id', 'DESC')
            ->paginate($request->paginate);
        }
        return view('admin_lte.server-error-logs.logs', ['data' => $data]);
    }
}
