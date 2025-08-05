<?php

namespace App\Http\Controllers\Admin;

use App\Exports\FinanceAgreementNewExport;
use App\Models\FinanceAgreementNew;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class FinanceAgreementNewController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!auth()->user()->can('financing_agreement.list')) {
            abort(403, 'Unauthorized action.');
        }
        $model = new FinanceAgreementNew();
        $columnNames = $model->getConnection()->getSchemaBuilder()->getColumnListing('financier_agreement_type_new');
        $valueToMove = 'financier_agreement_name';
        $index = array_search($valueToMove, $columnNames);
        if ($index !== false) {
            array_splice($columnNames, $index, 1);
            array_unshift($columnNames, $valueToMove);
        }
        $unwantedItems = array('id', 'created_at', 'updated_at');
        $columnNames = array_diff($columnNames, $unwantedItems);
        $datas = FinanceAgreementNew::all();
        return view('financier_agreement_type_new.index', compact('datas', 'columnNames'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (FinanceAgreementNew::count() === 0) {
            return redirect()->route('admin.finance-agreement-master.index')->with([
                'status' => 'Empty data',
                'class' => 'info',

            ]);
        } else {
            return Excel::download(new FinanceAgreementNewExport, 'FinancierAgreementMapping.xls');
        }
    }

    public function store(Request $request)
    {
        $model = new FinanceAgreementNew;
        $columnNames = $model->getConnection()->getSchemaBuilder()->getColumnListing('financier_agreement_type_new');

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
                    return redirect()->route('admin.finance-agreement-master.index')->with([
                        'status' =>  $msg,
                        'class' => 'info',
                    ]);
                } else {
                    $allData = Excel::toArray([], $file)[0];
                    if ($allData[0][0] == 'financier_agreement_name') {
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
                                $upload = FinanceAgreementNew::UpdateOrCreate(['financier_agreement_name' => $data[0]], $newArray); // first column will be relation column
                            }
                        }
                    } else {
                        return redirect()->route('admin.finance-agreement-master.index')->with([
                            'status' => "First column should be 'financier_agreement_name'",
                            'class' => 'danger',
                        ]);
                    }
                }
                if (isset($upload)) {
                    return redirect()->route('admin.finance-agreement-master.index')->with([
                        'status' => ($ColMisMatch == 1) ? 'Finance agreement details added, except for the columns which did not match with the columns of the table' : 'Finance agreement details added..!',
                        'class' => 'success',

                    ]);
                } else {
                    return redirect()->route('admin.finance-agreement-master.index')->with([
                        'status' => 'Something went wrong..',
                        'class' => 'danger',
                    ]);
                }
            } else {
                return redirect()->route('admin.finance-agreement-master.index')->with([
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
     * @param  \App\Models\FinanceAgreementNew  $financeAgreementNew
     * @return \Illuminate\Http\Response
     */
    public function show(FinanceAgreementNew $financeAgreementNew)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\FinanceAgreementNew  $financeAgreementNew
     * @return \Illuminate\Http\Response
     */
    public function edit(FinanceAgreementNew $financeAgreementNew)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateFinanceAgreementNewRequest  $request
     * @param  \App\Models\FinanceAgreementNew  $financeAgreementNew
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, FinanceAgreementNew $financeAgreementNew)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\FinanceAgreementNew  $financeAgreementNew
     * @return \Illuminate\Http\Response
     */
    public function destroy(FinanceAgreementNew $financeAgreementNew)
    {
        //
    }
}
