<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ErrorListExport;
use App\Http\Controllers\Controller;
use App\Models\ErrorList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ErrorListController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!auth()->user()->can('error_master.list')) {
            return abort(403, 'Unauthorized action.');
        }
        $model = new ErrorList;
        $columnNames = $model->getConnection()->getSchemaBuilder()->getColumnListing('error_list');
        $unwantedItems = array('id', 'created_at', 'updated_at');
        $columnNames = array_diff($columnNames, $unwantedItems);
        $datas = ErrorList::all();
        return view('error_list.index', compact('datas', 'columnNames'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (ErrorList::count() === 0) {
            return redirect()->route('admin.error-list-master.index')->with([
                'status' => 'Empty data',
                'class' => 'info',

            ]);
        } else {
            return Excel::download(new ErrorListExport, 'ErrorList.xls');
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
        $model = new ErrorList;
        $columnNames = $model->getConnection()->getSchemaBuilder()->getColumnListing('error_list');
        $unwantedItems = array('id', 'created_at', 'updated_at');
        $columnNames = array_diff($columnNames, $unwantedItems);

        $file = $request->file('excelfile');
        $extension = $file->getClientOriginalExtension();
        $counter = 0;
        try {
            if ($extension == 'xlsx' || $extension == 'xls') {
                $path = $request->file('excelfile')->getRealPath();
                $data = Excel::toArray([], $path, null, \Maatwebsite\Excel\Excel::XLSX)[0];
                $difference = array_diff($columnNames, $data[0]);

                if (empty($difference)) {
                    $uniqueValues1 = array_unique($columnNames);
                    if (count($data[0]) !== count($uniqueValues1)) {
                        return redirect()->route('admin.error-list-master.index')->with([
                            'status' =>  'Found duplicates columns..!',
                            'class' => 'info',
                        ]);
                    }
                } else {
                    // Arrays are not equal
                    return redirect()->route('admin.error-list-master.index')->with([
                        'status' => 'Column names are not matching..!',
                        'class' => 'info',
                    ]);
                }

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
                    return redirect()->route('admin.error-list-master.index')->with([
                        'status' =>  $msg,
                        'class' => 'info',
                    ]);
                } else {
                    $allData = Excel::toArray([], $file)[0];
                    // if ($allData[0][0] == 'gender_name') {
                    foreach ($allData as $data) {
                        if ($counter++ == 0) continue; //  to skip the first row of the $allData array, which usually contains column headings and not actual data. 

                        $newArray = [];
                        // $ColMisMatch = 0;
                        foreach ($data as $key => $value) {
                            if (in_array(trim($allData[0][$key]), $columnNames)) {
                                $newArray[trim($allData[0][$key])] = trim($value);
                            } else {
                                // $ColMisMatch = 1;
                                return redirect()->route('admin.error-list-master.index')->with([
                                    'status' => 'Column names are not matching..!',
                                    'class' => 'info',
                                ]);
                            }
                        }
                        // $slice = array_slice($newArray, 1);
                        // $nullElements = array_filter($slice);
                        // dd($newArray);
                        // if ((trim($data[0]) != null) && (!empty($nullElements))) {
                        $upload = ErrorList::Create($newArray);
                        // }
                    }
                    // dd('j');
                    // } else {
                    //     return redirect()->route('admin.gender-master.index')->with([
                    //         'status' => "First column should be 'gender_name'",
                    //         'class' => 'danger',
                    //     ]);
                    // }
                }
                if (isset($upload)) {
                    return redirect()->route('admin.error-list-master.index')->with([
                        'status' => 'Error List added..!',
                        'class' => 'success',

                    ]);
                } else {
                    return redirect()->route('admin.error-list-master.index')->with([
                        'status' => 'Something went wrong..',
                        'class' => 'danger',
                    ]);
                }
            } else {
                return redirect()->route('admin.error-list-master.index')->with([
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
     * @param  \App\Models\ErrorList  $ErrorList
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $read = 'readonly';
        $errorList = DB::table('error_list')->where('id', $id)->first();
        return view('error_list.edit', compact('errorList', 'id', 'read'));
    }
    

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\ErrorList  $ErrorList
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $read='';
        $errorList = DB::table('error_list')->where('id', $id)->first();
        return view('error_list.edit', compact('errorList', 'id', 'read'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\ErrorList  $ErrorList
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        try {
            $errorList = ErrorList::find($id);
            $errorList->update([
                'error_name' => $request->error_name,
                'error_code' => $request->error_code,
                'ic_name' => $request->ic_name,
                'status' => $request->status == 'Active' ? 'Y' : 'N',
                'updated_at' => now(),
            ]);
            if ($errorList) {
                return redirect()->route('admin.error-list-master.index')->with([
                    'status' => 'Updated Successfully..!',
                    'class' => 'success',
                ]);
            } else {
                return redirect()->route('admin.error-list-master.index')->with([
                    'status' => 'Something went wrong..!',
                    'class' => 'danger',
                ]);
            }
        } catch (\Exception $e) {
            return redirect()->back()->with([
                'status' => 'Something went wrong ' . $e->getMessage() . '...!',
                'class' => 'danger',
            ]);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\ErrorList  $ErrorList
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        try {
            if (ErrorList::find($id)->delete()) {
                return redirect()->route('admin.error-list-master.index')->with([
                    'status' => 'Deleted Successfully..!',
                    'class' => 'success',
                ]);
            } else {
                return redirect()->route('admin.error-list-master.index')->with([
                    'status' => 'Something went wrong..!',
                    'class' => 'danger',
                ]);
            }
        } catch (\Exception $e) {
            return redirect()->back()->with([
                'status' => 'Something went wrong ' . $e->getMessage() . '...!',
                'class' => 'danger',
            ]);
        }
    }
}
