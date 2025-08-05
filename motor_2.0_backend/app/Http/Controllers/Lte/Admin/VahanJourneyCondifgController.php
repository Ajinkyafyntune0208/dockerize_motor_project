<?php

namespace App\Http\Controllers\Lte\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Mmvproposaljourneyblocker;

class VahanJourneyCondifgController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public $blockerType = [
        'Segment',
        'Make',
        'Model',
        'Fuel',
        'B2C',
        'P',
        'E',
        'U',
        'MISP',
        'PARTNER'
    ];

    public function index()
    {
        if (!auth()->user()->can('vahan_journey_configurations.list')) {
            abort(403, 'Unauthorized action.');
        }
        $pages = Mmvproposaljourneyblocker::where('value', 'Y')->get()->pluck('name')->toArray();
        $blockerType = $this->blockerType;
        return view('admin_lte.vahan-journey-config.index', compact('pages','blockerType'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        foreach ($this->blockerType as $value) {
            Mmvproposaljourneyblocker::updateOrCreate([
                'name' => $value,
            ], [
                'value' => $request->has($value) ? 'Y' : 'N'
            ]);
        }
        return redirect()->back()->with('success', 'Configuration saved successfully.');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
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
        //
    }
}
