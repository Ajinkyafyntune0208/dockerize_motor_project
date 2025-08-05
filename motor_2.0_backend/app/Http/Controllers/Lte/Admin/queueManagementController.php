<?php

namespace App\Http\Controllers\Lte\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\failedJob;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Carbon;

class QueueManagementController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if (!auth()->user()->can('queue_management.list')) {
            abort(403, 'Unauthorized action.');
        }
        $filters = [];
        if (!empty($request->from) && !empty($request->to)) {
            $filters = [
                ['failed_at', '>=', Carbon::parse($request->from)->startOfDay()],
                ['failed_at', '<=', Carbon::parse($request->to)->endOfDay()],
            ];
        }
        if (!empty($request->queue)) {
            $filters[] = ['queue', '=', $request->queue];
        }
        if (!empty($request->UUID)) {
            $filters[] = ['uuid', '=', $request->UUID];
        }
        $reports = failedJob::where($filters)->orderBy('id', 'desc')->limit(100)->get();
        return view('admin_lte.queue_management.index', compact('reports'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request) // this is for selected checkbox
    {
        try {
            $selectedCheckboxes = $request->input('selectedCheckboxes');
            $action = $request->input('action');
            if ($action == 'retry') {
                // $selectedCheckboxes = implode(' ', $selectedCheckboxes);
                Artisan::call('queue:retry', ['id' => $selectedCheckboxes]);
            } else if ($action == 'delete') {
                // $selectedCheckboxes = implode(' ', $selectedCheckboxes);
                Artisan::call('queue:forget', ['id' => $selectedCheckboxes]);
            }
            return response()->json([
                'status' => true,
                'message' => $action . 'successfully.',
                'selectedCheckboxes' => $selectedCheckboxes,
                'action' => $action
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $log = failedJob::find($id);
        return view('admin_lte.queue_management.show', compact('log'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id) // this is for  individual retry
    {
        Artisan::call('queue:retry', ['id' => $id]);
        return redirect()->back();
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        Artisan::call('queue:forget', ['id' => $id]);
        return redirect()->back();
    }
}
