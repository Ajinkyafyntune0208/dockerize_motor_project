<?php

namespace App\Http\Controllers\Lte\Admin;

use App\Http\Controllers\Controller;
use App\Models\AgentsImdConfig;
use App\Models\MasterCompany;
use App\Models\MasterProductSubType;
use App\Models\PosConfigLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class PosConfigController extends Controller
{
    public function index(Request $request)
    {
    
        // if (!auth()->user()->can('configurator.pos-imd')) {
        //     return abort(403, 'Unauthorized action.');
        // }
        
        $posList = $this->getPos($request);
        $posList = json_decode($posList->content(), true);
        $posList = $posList['data'];

  
        $sections = $this->getSections($request);
        $sections = json_decode($sections->content(), true);
        $sections = $sections['data'];

        $ics = $this->getIcs($request);
        $ics = json_decode($ics->content(), true);
        $ics = $ics['data'];


        $fields = $this->getFields($request);
        $fields = json_decode($fields->content(), true);
        $field = $fields['data'];
        $fields = json_encode($field);

 
        if ($request->method() == 'GET') {
            return view('admin_lte.pos-config.credentials.index', compact('posList', 'sections', 'ics', 'fields'));
        } else {
            $data = $request->input();
            $data['source'] = 'MOTOR';
            request()->replace($data);

            $response = $this->storePosConfig($request);
            $response = json_decode($response->content(), true);
            if ($response['status'] ?? false) {
                return redirect()->back()->with('success', $response['message']);
            } else {
                $message = $response['message'];
                if (is_array($response['message'])) {
                    $message = reset($message)[0] ?? $message;
                }
                return redirect()->back()->with('error', $message);
            }
        }
    }

    public function getFields(Request $request)
    {
        $fields = [
            36 => ['userName' => 'User Name', 'password' => "Password"],
            44 => ['code' => 'This is OIC field'],
        ];

        return response()->json([
            'status' => true,
            'data' => $fields
        ]);
    }

    public function getPos(Request $request)
    {
        $posList = Http::get(config('DASHBOARD_GET_AGENT_LINK'));
        $posList = $posList->json();
        $posList = $posList['data'] ?? [];
        $finalPos = [];

        foreach ($posList as $value) {
            $finalPos[$value['agent_id']] = $value['agent_name'];
        }
        $posList = $finalPos;

        return response()->json([
            'status' => true,
            'data' => $posList
        ]);
    }

    public function getIcs(Request $request)
    {
        $ics = MasterCompany::select('company_alias', 'company_id')
        ->whereNotNull('company_alias')
        ->get()
        ->toArray();

        return response()->json([
            'status' => true,
            'data' => $ics,
        ]);
    }

    public function getSections(Request $request)
    {
        $sections = MasterProductSubType::select('product_sub_type_code', 'product_sub_type_id')
        ->whereNotNull('product_sub_type_id')
        ->get()
        ->toArray();

        return response()->json([
            'status' => true,
            'data' => $sections,
        ]);
    }
    
    public function getPosConfig(Request $request)
    {
        $posList = $this->getPos($request);
        $posList = json_decode($posList->content(), true);
        $posList = $posList['data'];


        $sections = $this->getSections($request);
        $sections = json_decode($sections->content(), true);
        $sections = $sections['data'];

        $ics = $this->getIcs($request);
        $ics = json_decode($ics->content(), true);
        $ics = $ics['data'];

        $validator = Validator::make($request->all(), [
            'section' => 'nullable|array|in:'.implode(',', array_column($sections, 'product_sub_type_id')),
            'pos' => 'nullable|array|in:'.implode(',', array_keys($posList)),
            'insuranceCompany' => 'nullable|array|in:'.implode(',', array_column($ics, 'company_id')),
        ]);

        if($validator->fails()){
            return response()->json([
                'status' => false,
                'message' => $validator->errors()
            ], 400);
        }

        $configs = AgentsImdConfig::select('id', 'agent_id', 'master_product_sub_type_id', 'credentials', 'ic_id')
        ->with([
            'agent_detail' => function ($query) {
                return $query->select([
                    'agent_id',
                    'agent_name',
                    'user_name',
                    'unique_number'
                ]);
            },
            'product_sub_type' => function ($query) {
                return $query->select([
                    'product_sub_type_id',
                    'product_sub_type_code',
                    'product_sub_type_name',
                ]);
            },
            'insurance_company' => function ($query) {
                return $query->select([
                    'company_id',
                    'company_name',
                    'company_alias',
                ]);
            },
        ])
        ->where('status', true)
        ->when(!empty($request->section), function ($query) {
            $query->whereIn('master_product_sub_type_id', request()->section);
        })
        ->when(!empty($request->pos), function ($query) {
            $query->whereIn('agent_id', request()->pos);
        })
        ->when(!empty($request->insuranceCompany), function ($query) {
            $query->whereIn('ic_id', request()->insuranceCompany);
        })
        ->get();

        if (!empty($configs)) {
            return response()->json([
                'status' => true,
                'data' => $configs,
                'message' => count($configs) . ' records found'
            ], 200);
        }

        return response()->json([
            'status' => false,
            'message' => 'No data found'
        ], 200);
    }

    public function destroy(Request $request)
    {
        $response = $this->destroyConfig($request, $request->deleteId);
        $response = json_decode($response->content(), true);
        if ($response['status'] ?? false) {
            return redirect()->back()->with('success', $response['message']);
        } else {
            $message = $response['message'] ?? 'Something went wrong';
            return redirect()->back()->with('error', $message);
        }
    }

    public function storePosConfig(Request $request)
    {
        $posList = $this->getPos($request);
        $posList = json_decode($posList->content(), true);
        $posList = $posList['data'];


        $sections = $this->getSections($request);
        $sections = json_decode($sections->content(), true);
        $sections = $sections['data'];

        $ics = $this->getIcs($request);
        $ics = json_decode($ics->content(), true);
        $ics = $ics['data'];


        $fields = $this->getFields($request);
        $fields = json_decode($fields->content(), true);
        $field = $fields['data'];

        $configLog = PosConfigLog::create([
            'type' => 'IMD',
            'user_id' => auth()->user()?->id,
            'operation_type' => 'UPDATE',
            'url' => $request->url(),
            'request' => json_encode($request->input())
        ]);

        $validator = Validator::make($request->all(), [
            'section' => 'required|array|in:' . implode(',', array_column($sections, 'product_sub_type_id')),
            'pos' => 'required|array|in:' . implode(',', array_keys($posList)),
            'insuranceCompany' => 'required|array|in:' . implode(',', array_column($ics, 'company_id')),
            'creds' => 'required|array',
            'source' => 'required|in:MOTOR,DASHBOARD'
        ]);

        if ($validator->fails()) {
            $response = $validator->errors();
            $configLog->response = json_encode($response);
            $configLog->save();
            return response()->json([
                'status' => false,
                'message' => $response
            ]);
        }

        $section = $request->section;
        $pos = $request->pos;
        $insuranceCompany = $request->insuranceCompany;
        $creds = $request->creds;

        foreach ($pos as $p) {

            foreach ($section as $s) {
                foreach ($insuranceCompany as $ic) {
                    $store = [
                        'source' => $request->source,
                        'created_by' => auth()->user()?->id,
                        'updated_by' => auth()->user()?->id,
                        'status' => true
                    ];
                    $check = [
                        'agent_id' => $p,
                        'master_product_sub_type_id' => $s,
                        'ic_id' => $ic
                    ];
                    $credentials = [];
                    if (!empty($field[$ic])) {
                        foreach ($field[$ic] as $tag => $tagValue) {
                            $credentials[$tag] = $creds[$ic][$tag] ?? null;
                        }
                    }
                    $store['credentials'] = $credentials;
                    AgentsImdConfig::updateOrCreate($check, $store);
                }
            }
        }

        $response = [
            'status' => true,
            'message' => 'Credentials updated successfully'
        ];

        $configLog->response = json_encode($response);
        $configLog->save();

        return response()->json($response);

    }

    public function destroyConfig(Request $request, $id)
    {
        $configLog = PosConfigLog::create([
            'type' => 'IMD',
            'user_id' => auth()->user()?->id,
            'operation_type' => 'DELETE',
            'url' => $request->url(),
            'request' => json_encode($request->input())
        ]);

        if (!empty($id)) {
            AgentsImdConfig::where('id', $id)->update([
                'status' => false
            ]);

            $response = [
                'status' => true,
                'message' => 'Credentials deleted successfully'
            ];
            $configLog->response = json_encode($response);
            $configLog->save();

            return response()->json($response);
        }

        $response = [
            'status' => true,
            'message' => 'Something went wrong'
        ];
        $configLog->response = json_encode($response);
        $configLog->save();

        return response()->json($response);
    }
}
