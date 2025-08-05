<?php

namespace App\Http\Controllers\Lte\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MdmMaster;

class MdmMasterController extends Controller
{
    public function index(Request $request)
    {

        $data = MdmMaster::all();

        $editItem = null;
        if ($request->has('edit_id')) {
            $editItem = MdmMaster::find($request->edit_id);
        }

        return view('admin_lte.mdm_master.index', compact('data', 'editItem'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'mdm_allowed_table' => 'required|string|max:255',
        ]);

        MdmMaster::create($request->only('mdm_allowed_table'));

        return redirect()->route('admin.mdm_master.index')->with('success', 'Created successfully!');
    }

    public function update(Request $request)
    {
        $request->validate([
            'mdm_allowed_table' => 'required|string|max:255',
            'id' => 'required|exists:mdm_master,id',
        ]);

        $item = MdmMaster::findOrFail($request->id);
        $item->update($request->only('mdm_allowed_table'));

        return redirect()->route('admin.mdm_master.index')->with('success', 'Updated successfully!');
    }

    public function destroy(Request $request)
    {
        $item = MdmMaster::findOrFail($request->id);
        $item->delete();

        return redirect()->route('admin.mdm_master.index')->with('success', 'Deleted successfully!');
    }
}
