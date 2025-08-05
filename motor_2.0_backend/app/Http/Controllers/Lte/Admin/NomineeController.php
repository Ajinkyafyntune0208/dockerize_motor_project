<?php

namespace App\Http\Controllers\Lte\Admin;

use App\Http\Controllers\Controller;
use App\Models\NomineeRelationshipNew;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Schema;
use App\Exports\NomineeExport;

class NomineeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!auth()->user()->can('nominee_relationship.list')) {
            abort(403, 'Unauthorized action.');
        }
        $model = new NomineeRelationshipNew;
        $columnNames = $model->getConnection()->getSchemaBuilder()->getColumnListing('nominee_relationship_new');
        $valueToMove = 'relation_name';
        $index = array_search($valueToMove, $columnNames);
        if ($index !== false) {
            array_splice($columnNames, $index, 1);
            array_unshift($columnNames, $valueToMove);
        }
        $unwantedItems = array('id', 'created_at', 'updated_at');
        $columnNames = array_diff($columnNames, $unwantedItems);
        $datas = NomineeRelationshipNew::all();
        return view('admin_lte.nominee-master.index', compact('datas', 'columnNames'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (NomineeRelationshipNew::count() === 0) {
            return redirect()->route('admin.nominee-master.index')->with([
                'status' => 'Empty data',
                'class' => 'info',

            ]);
        } else {
            return Excel::download(new NomineeExport, 'NomineeRelationship.xls');
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
        $model = new NomineeRelationshipNew;
        $columnNames = $model->getConnection()->getSchemaBuilder()->getColumnListing('nominee_relationship_new');

        $file = $request->file('excelfile');
        $extension = $file->getClientOriginalExtension();
        $counter = 0;
        try {
            if ($extension == 'xlsx' || $extension == 'xls') {
                $path = $request->file('excelfile')->getRealPath();
                $data = Excel::toArray([], $path, null, \Maatwebsite\Excel\Excel::XLSX)[0];
                $isData = true;
                if (count($data) >= 1) {

                    if (count($data) == 1 && empty(array_filter($data[0]))) {
                        // The file is completely blank
                        $msg = 'The file is completely blank';
                        $isData = false;
                    } elseif (count($data) >= 2) {
                        // There is at least one row of data in the file
                        $secondRow = array_filter($data[1]);
                        if (!empty($secondRow)) {
                            // There is data after the first row
                            $isData = true;
                        } else {
                            // There is no data after the first row
                            $msg = 'There is no data after the first row';
                            $isData = false;
                        }
                    } else {
                        $msg = 'There is no data after the first row';
                        $isData = false;
                    }
                }
                if (!$isData) {
                    return redirect()->route('admin.nominee-master.index')->with([
                        'status' =>  $msg,
                        'class' => 'info',
                    ]);
                } else {
                    $allData = Excel::toArray([], $file)[0];
                    if ($allData[0][0] == 'relation_name') {
                        foreach ($allData as $data) {
                            if ($counter++ == 0) continue; //  to skip the first row of the $allData array, which usually contains column headings and not actual data. 

                            $newArray = [];
                            $ColMisMatch = 0;
                            foreach ($data as $key => $value) {
                                if (in_array(trim($allData[0][$key]), $columnNames)) {
                                    $newArray[trim($allData[0][$key])] = trim($value);
                                } else {
                                    $ColMisMatch = 1;
                                }
                            }
                            $slice = array_slice($newArray, 1);
                            $nullElements = array_filter($slice);
                            if ((trim($data[0]) != null) && (!empty($nullElements))) {
                                $upload = NomineeRelationshipNew::UpdateOrCreate(['relation_name' => $data[0]], $newArray); // first column will be relation column
                            }
                        }
                    } else {
                        return redirect()->route('admin.nominee-master.index')->with([
                            'status' => "First column should be 'relation_name'",
                            'class' => 'danger',
                        ]);
                    }
                }
                if (isset($upload)) {
                    return redirect()->route('admin.nominee-master.index')->with([
                        'status' => ($ColMisMatch == 1) ? 'Nominee details added, except for the columns which did not match with the columns of the table' : 'Nominee Details added..!',
                        'class' => 'success',

                    ]);
                } else {
                    return redirect()->route('admin.nominee-master.index')->with([
                        'status' => 'Something went wrong..',
                        'class' => 'danger',
                    ]);
                }
            } else {
                return redirect()->route('admin.nominee-master.index')->with([
                    'status' => 'Choose excel file format',
                    'class' => 'danger',
                ]);
            }
        } catch (\Exception $e) {
            return redirect()->back()->with([
                'status' => 'Something wents wrong ' . $e->getMessage() . '...!',
                'class' => 'danger',
            ]);
        }
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
