<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IcErrorHandling;
use App\Models\MasterCompany;
use App\Models\ProposalIcErrorHandling;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class IcErrorHandllingController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if (!auth()->user()->can('ic-error-handling.list')) {
            abort(403, 'Unauthorized action.');
        }
        //$data = IcErrorHandling::orderBy('updated_at', 'desc')->get();
        $companies = MasterCompany::orWhereNotNull('company_alias')
            ->orderBy('company_alias', 'asc')
            ->get();
        if ($request->type == 'proposal') {
            $model = new ProposalIcErrorHandling();
        } else {
            $model = new IcErrorHandling();
        }
        $data = $model::where('company_alias', $request->company_alias)
            ->where('section', $request->section)
            ->where('status', $request->status)
            ->paginate(10)->withQueryString();
        return view('ic_error.index', compact('data', 'companies'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $companies = MasterCompany::orWhereNotNull('company_alias')
            ->orderBy('company_alias', 'asc')
            ->get();
        if (isset($request->file) && $request->file == 'csv_file') {
            return view('ic_error.upload', compact('companies'));
        }
        return view('ic_error.create', compact('companies'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $is_excel = false;
        if ($request->hasFile('error_handling_file')) {
            $d = $request->file('error_handling_file');
            if (!in_array($d->getClientOriginalExtension(), ['xlsx'])) {
                return redirect(url()->previous())->with([
                    'status' => 'The file format should be Excel xlsx',
                    'class' => 'danger',
                ]);
            }
            $is_excel = true;
            $insert_data = \Maatwebsite\Excel\Facades\Excel::toArray(new \App\Imports\IcErrorImport, $request->file('error_handling_file'))[0];
        } else {
            $rules = [
                'company_alias' => 'required',
                'section' => 'required',
                'ic_error' => 'required',
                'custom_error' => 'required',
                'status' => 'required',
                'type' => 'required',
            ];
            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator->errors())->withInput();
            }
            $insert_data[] = [
                'company_alias' => $request->company_alias,
                'section' => $request->section,
                'ic_error' => trim($request->ic_error),
                'checksum' => checksum_encrypt(trim($request->ic_error)),
                'custom_error' => trim($request->custom_error),
                'status' => $request->status,
                'type' => $request->type,
            ];
        }
        foreach ($insert_data as $data) {
            if (trim($data['company_alias']) == '' || $data['company_alias'] == null || is_null($data['company_alias'])) {
                break;
            }
            $row_data = [
                'company_alias' => $data['company_alias'],
                'section' => $data['section'],
                'ic_error' => trim($data['ic_error']),
                'checksum' => checksum_encrypt(trim($data['ic_error'])),
                'custom_error' => trim($data['custom_error']),
                'status' => $data['status'],
            ];
            if (!in_array($data['status'], ['Y', 'N'])) {
                return redirect(url()->previous())->with([
                    'status' => 'Status column should contain value either "Y" or "N"',
                    'class' => 'danger',
                ]);
            }
            $type = $data['type'] ?? '';
            if ($type == 'proposal') {
                $model = new ProposalIcErrorHandling();
            } else if ($type == 'quote') {
                $model = new IcErrorHandling();
            } else {
                return redirect(url()->previous())->with([
                    'status' => 'Type column should contain value either "proposal" or "quote"',
                    'class' => 'danger',
                ]);
            }
            if (isset($data['type'])) {
                unset($data['type']);
            }

            if ($is_excel) {
                // If record exists then delete that record
                $existingRecordIds = $model::where([
                    ['ic_error', '=', trim($data['ic_error'])],
                    ['checksum', '=', checksum_encrypt(trim($data['ic_error']))],
                    ['company_alias', '=', $data['company_alias']],
                    ['section', '=', $data['section']],
                ])->select('id')->get()->pluck('id');
                if (!$existingRecordIds->isEmpty()) {
                    $model::whereIn('id', $existingRecordIds)->delete();
                }
            }
            $model::updateOrCreate($row_data, $row_data);
        }
        return redirect()->route('admin.ic-error-handling.index', $is_excel ? [] : $insert_data[0])->with([
            'status' => 'Record Created Successfully..!',
            'class' => 'success',
        ]);
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
        if (request()->type == 'proposal') {
            $edit_data = ProposalIcErrorHandling::where('id', $id)->first();
        } else {
            $edit_data = IcErrorHandling::where('id', $id)->first();
        }
        if (empty($edit_data)) {
            // return redirect()->route('admin.ic-error-handling.index')->with([
            return redirect(url()->previous())->with([
                'status' => implode(', ', array_filter([$id, request()->type])) . ' : Couldn\'t find the selected record to edit.',
                'class' => 'danger',
            ]);
        }
        // $companies = MasterCompany::orWhereNotNull('company_alias')
        //             ->orderBy('company_alias','asc')
        //             ->get();
        return view('ic_error.edit', compact('edit_data'));
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
        if ($request->type == 'proposal') {
            $table = ProposalIcErrorHandling::find($id);
        } else {
            $table = IcErrorHandling::find($id);
        }
        if (empty($table)) {
            return redirect()->route('admin.ic-error-handling.index')->with([
                'status' => 'Something went wrong while updating the selected custom error message.',
                'class' => 'danger',
            ]);
        }
        $rules = [
            'error_disp' => 'required',
            'status' => 'required',
        ];
        $custom_message= [
            'error_disp.required' => 'The custom error field is required.',
        ];
        $validator = Validator::make($request->all(), $rules,$custom_message);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator->errors())->withInput();
        }
        $table->update([
            // 'ic_error' => $request->error,
            'custom_error' => trim($request->error_disp),
            'status' => $request->status,
        ]);
        return redirect()->route('admin.ic-error-handling.index')->with([
            'status' => 'Record updated Successfully! ID : ' . $id,
            'class' => 'success',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id)
    {
        if ($request->type == 'proposal') {
            $model = new ProposalIcErrorHandling();
            $record = ProposalIcErrorHandling::find($id);
        } else {
            $model = new IcErrorHandling();
            $record = IcErrorHandling::find($id);
        }
        if (empty($record)) {
            return redirect(url()->previous())->with([
                'status' => 'An error occured while deleting the record.',
                'class' => 'danger',
            ]);
        } else {
            $model::destroy($id);
            return redirect()->route('admin.ic-error-handling.index')->with([
                'status' => 'Record Deleted Successfully..!',
                'class' => 'danger',
            ]);
        }
    }
}
