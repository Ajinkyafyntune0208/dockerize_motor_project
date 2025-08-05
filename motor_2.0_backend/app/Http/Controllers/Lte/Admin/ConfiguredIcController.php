<?php

namespace App\Http\Controllers\Lte\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\IcConfigurator;
use App\Models\IcIntegrationType;
use App\Models\PremCalcActivation;
use App\Models\PremCalcConfigurator;
use App\Models\PremCalcLabel;
use App\Models\PremCalcAttributes;
use App\Models\PremCalcBucket;
use App\Models\PremCalcBucketList;
use App\Models\PremCalcFormula;
use App\Models\PremCalcPlaceholder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConfiguredIcController extends Controller
{

    public function index(Request $request)
    {
        if (!auth()->user()->can('premium_calculation_configurator.list')) {
            return response('unauthorized action', 401);
        }

        $ics = IcIntegrationType::with(['activation'])->get();
        $alias = IcIntegrationType::select('ic_alias', 'integration_type', 'segment', 'business_type')
            ->distinct()
            ->get()
            ->sortBy([
                'ic_alias',
                'integration_type',
                'segment',
                'business_type'
            ]);
             
        $activeIcs = IcIntegrationType::select(
            'ic_integration_type.ic_alias',
            'ic_integration_type.integration_type',
            'ic_integration_type.segment',
            'ic_integration_type.business_type',
            'ic_integration_type.slug',
            'prem_calc_activations.slug AS pca_slug',
            'prem_calc_activations.is_active AS pca_active',
            DB::raw('IFNULL(ic_version_activations.is_active, 0) AS is_active')
        )
            ->leftJoin('prem_calc_activations', 'prem_calc_activations.slug', '=', 'ic_integration_type.slug')
            ->leftJoin('ic_version_activations', 'ic_version_activations.slug', '=', 'ic_integration_type.slug')
            ->having('is_active', '=', 1)
            ->get();

        $inactiveIcs = IcIntegrationType::select(
            'ic_integration_type.ic_alias',
            'ic_integration_type.integration_type',
            'ic_integration_type.segment',
            'ic_integration_type.business_type',
            'ic_integration_type.slug',
            'prem_calc_activations.slug AS pca_slug',
            'prem_calc_activations.is_active AS pca_active',
            DB::raw('IFNULL(ic_version_activations.is_active, 0) AS is_active')
        )
            ->leftJoin('prem_calc_activations', 'prem_calc_activations.slug', '=', 'ic_integration_type.slug')
            ->leftJoin('ic_version_activations', 'ic_version_activations.slug', '=', 'ic_integration_type.slug')
            ->having('is_active', '=', 0)
            ->get();

           
        return view('admin_lte.ics.index', compact('ics', 'alias', 'activeIcs', 'inactiveIcs'));
    }

    public function cloneIC(Request $request)
    {
        
        $action = $request->input('dynamicData');

        if ($action === 'fetch_data') {
            $icAlias = $request->input('ic_alias');
            $integrationType = $request->input('integration_type');
            $segment = $request->input('segment');
    
            $integrationTypes = IcIntegrationType::select('integration_type')
                ->where('ic_alias', $icAlias)
                ->distinct()
                ->pluck('integration_type', 'integration_type')
                ->toArray();
    
            $segments = IcIntegrationType::select('segment')
                ->where('ic_alias', $icAlias)
                ->where('integration_type', $integrationType)
                ->distinct()
                ->pluck('segment')
                ->toArray();
    
            $businessTypes = IcIntegrationType::select('business_type')
                ->where('ic_alias', $icAlias)
                ->where('integration_type', $integrationType)
                ->where('segment', $segment)
                ->distinct()
                ->pluck('business_type')
                ->toArray();
    
            return response()->json([
                'integrationTypes' => $integrationTypes,
                'segments' => $segments,
                'businessTypes' => $businessTypes
            ]);
        }
            try {
                $validated = $request->validate([
                    'ic_slug' => 'required|string',
                    'ic_alias' => 'required|string',
                    'integration_type' => 'required|string',
                    'segment' => 'required|string',
                    'business_type' => 'required|string',
                    'override_exists' => 'required|in:yes,no',
                ]);
            } catch (\Illuminate\Validation\ValidationException $e) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed.',
                    'errors' => $e->validator->errors(),
                ], 422);
            }

            $originalIC = IcIntegrationType::where('slug', base64_decode($validated['ic_slug']))->firstOrFail();

            $newConfigData = [
                'ic_alias' => $validated['ic_alias'],
                'integration_type' => $validated['integration_type'],
                'segment' => $validated['segment'],
                'business_type' => $validated['business_type'],
                'created_by' => auth()->user()->id,
                'updated_by' => auth()->user()->id,
            ];

            if ($newConfigData['ic_alias'] === $originalIC->ic_alias &&
                    $newConfigData['integration_type'] === $originalIC->integration_type &&
                    $newConfigData['segment'] === $originalIC->segment &&
                    $newConfigData['business_type'] === $originalIC->business_type) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Clonning for same combination IC is not permitted',
                    ], 400);
                }
                
            if ($validated['override_exists'] === 'yes') {
                PremCalcConfigurator::where('ic_alias', $newConfigData['ic_alias'])
                    ->where('integration_type', $newConfigData['integration_type'])
                    ->where('segment', $newConfigData['segment'])
                    ->where('business_type', $newConfigData['business_type'])
                    ->delete();
            }

            $existingConfigs = PremCalcConfigurator::where([
                ['ic_alias', '=', $originalIC->ic_alias],
                ['integration_type', '=', $originalIC->integration_type],
                ['segment', '=', $originalIC->segment],
                ['business_type', '=', $originalIC->business_type]
            ])->get();


            foreach ($existingConfigs as $config) {
                $presentData = PremCalcConfigurator::where('ic_alias', $newConfigData['ic_alias'])
                    ->where('integration_type', $newConfigData['integration_type'])
                    ->where('segment', $newConfigData['segment'])
                    ->where('business_type', $newConfigData['business_type'])
                    ->where('label_id', $config->label_id)
                    ->first();

                $exists = !empty($presentData);
                if ($validated['override_exists'] === 'yes') {

                    $newConfig = $config->replicate();
                    $data = $newConfig->toArray();

                    PremCalcConfigurator::updateOrCreate([
                        'ic_alias' => $newConfigData['ic_alias'],
                        'integration_type' =>  $newConfigData['integration_type'],
                        'segment' => $newConfigData['segment'],
                        'business_type' => $newConfigData['business_type'],
                        'label_id' => $config->label_id
                    ], [
                        'calculation_type' => $data['calculation_type'],
                        'attribute_id' => $data['attribute_id'],
                        'formula_id' => $data['formula_id'],
                        'custom_val' => $data['custom_val'],
                        'created_by' => Auth::user()->id,
                        'updated_by' => Auth::user()->id,

                    ]);
                } elseif ($validated['override_exists'] === 'no') {
                    if ($exists) {
                        continue;
                    } else {
                        $newConfig = $config->replicate();

                        $data = $newConfig->toArray();
                        PremCalcConfigurator::Create([
                            'ic_alias' => $newConfigData['ic_alias'],
                            'integration_type' =>  $newConfigData['integration_type'],
                            'segment' => $newConfigData['segment'],
                            'business_type' => $newConfigData['business_type'],
                            'label_id' => $config->label_id,
                            'calculation_type' => $data['calculation_type'],
                            'attribute_id' => $data['attribute_id'],
                            'formula_id' => $data['formula_id'],
                            'custom_val' => $data['custom_val'],
                            'created_by' => Auth::user()->id,
                            'updated_by' => Auth::user()->id,

                        ]);
                    }
                }
            }

            return response()->json([
                'status' => true,
                'message' => 'IC Configuration cloned successfully.'
            ]);
       
    }


    public function edit($ic)
    {
        if (!auth()->user()->can('premium_calculation_configurator.edit')) {
            return response('unauthorized action', 401);
        }
        $decodedId = base64_decode($ic);
        $icConfigurator = IcIntegrationType::with('activation')->where('slug', $decodedId)->firstOrFail();

        $labels = PremCalcLabel::select('id', 'label_name', 'label_group', 'label_key')
            ->get()
            ->groupBy('label_group')
            ->sortKeys();

        $formulas = PremCalcFormula::whereNull('deleted_at')->get();       

        $attributes = PremCalcAttributes::distinct()
            ->join('ic_integration_type', function ($join) {
                $join->on(DB::raw('prem_calc_attributes.ic_alias COLLATE utf8mb4_unicode_ci'), '=', DB::raw('ic_integration_type.ic_alias COLLATE utf8mb4_unicode_ci'));
            })
            ->join('prem_calc_mapped_attributes', 'prem_calc_attributes.id', '=', 'prem_calc_mapped_attributes.attribute_id')
            ->join('prem_calc_labels', 'prem_calc_mapped_attributes.label_id', '=', 'prem_calc_labels.id')
            ->where('ic_integration_type.ic_alias', $icConfigurator->ic_alias)
            ->where('prem_calc_attributes.segment', $icConfigurator->segment)
            ->where('prem_calc_attributes.business_type', $icConfigurator->business_type)
            ->select(
                'prem_calc_attributes.attribute_name',
                'prem_calc_attributes.attribute_trail',
                'prem_calc_labels.label_name',
                'prem_calc_labels.label_key',
                'prem_calc_mapped_attributes.attribute_id',
                'prem_calc_mapped_attributes.label_id'
            )
            ->get();

        // $existingConfig = PremCalcConfigurator::where('ic_alias', $icConfigurator->ic_alias)
        //     ->where('segment', $icConfigurator->segment)
        //     ->where('business_type', $icConfigurator->business_type)
        //     ->get()
        //     ->keyBy('label_id');

        // $existingConfig = PremCalcConfigurator::where('ic_alias', $icConfigurator->ic_alias)
        // ->where('segment', $icConfigurator->segment)
        // ->where('business_type', $icConfigurator->business_type)
        // ->groupBy('label_id') 
        // ->selectRaw('MIN(id) as id, label_id, ic_alias, segment, business_type, calculation_type, attribute_id, formula_id, custom_val, created_at, updated_at, created_by, updated_by, deleted_at') 
        // ->get()
        // ->keyBy('label_id');

        $existingConfig = PremCalcConfigurator::where('ic_alias', $icConfigurator->ic_alias)
        ->where('segment', $icConfigurator->segment)
        ->where('business_type', $icConfigurator->business_type)
        ->whereNull('deleted_at')
        ->groupBy('label_id')
        ->selectRaw('
            MIN(id) as id, 
            label_id, 
            ANY_VALUE(ic_alias) as ic_alias, 
            ANY_VALUE(segment) as segment, 
            ANY_VALUE(business_type) as business_type, 
            ANY_VALUE(calculation_type) as calculation_type, 
            ANY_VALUE(attribute_id) as attribute_id, 
            ANY_VALUE(formula_id) as formula_id, 
            ANY_VALUE(custom_val) as custom_val, 
            ANY_VALUE(created_at) as created_at, 
            ANY_VALUE(updated_at) as updated_at, 
            ANY_VALUE(created_by) as created_by, 
            ANY_VALUE(updated_by) as updated_by, 
            ANY_VALUE(deleted_at) as deleted_at
        ')
        ->get()
        ->keyBy('label_id');

        foreach ($labels as $groupLabel => $keys) {
            foreach ($keys as $key) {
                $keyId = $key->id;
                if ($existingConfig->has($keyId)) {
                    $config = $existingConfig[$keyId];
                    if ($config->attribute_id) {
                        $key->selected_type = 'attribute_name';
                        $attribute = $attributes->where('attribute_id', $config->attribute_id)->first();
                        if ($attribute) {
                            $key->selected_value = $attribute->attribute_name . ' (' . $attribute->attribute_trail . ')';
                        }
                    } elseif ($config->formula_id) {
                        $formula = $formulas->where('id', $config->formula_id)->first();
                        if ($formula) {
                            $key->selected_type = 'formula_name';
                            $key->selected_value = $formula->id;
                        }
                    } elseif ($config->custom_val) {
                        $key->selected_type = 'custom_val';
                        $key->selected_value = $config->custom_val;
                    } else {
                        $key->selected_type = '';
                        $key->selected_value = '';
                    }
                } else {
                    $key->selected_type = '';
                    $key->selected_value = '';
                }
            }
        }

        return view('admin_lte.ics.edit', compact('icConfigurator', 'labels', 'formulas', 'attributes', 'ic'));
    }


    public function update(Request $request, $encodedIc)
    {
        $ic = base64_decode($encodedIc);

        $rules = [
            'formula_name' => 'nullable|string|max:250',
            'custom_val' => 'nullable|string|max:250',
            'label_key' => 'nullable|string|max:250',
            'attribute_id' => 'nullable|string|max:250',
            'activation' => 'required|in:DISABLE,ENABLE'
        ];

        foreach ($request->all() as $key => $value) {
            if (str_ends_with($key, '_type')) {
                $fieldKey = substr($key, 0, -5);
                $rules["{$fieldKey}_attribute_name"] = "nullable|string|max:250|required_if:{$key},attribute_name";
                $rules["{$fieldKey}_formula_name"] = "nullable|string|max:250|required_if:{$key},formula_name";
                $rules["{$fieldKey}_custom_val"] = "nullable|string|max:250|required_if:{$key},custom_val";
            }
        }

        $validatedData = $request->validate($rules);

        $icInstance = IcIntegrationType::where('slug', $ic)->firstOrFail();      

        if (!$icInstance) {
            return redirect()->route('admin.ic-configuration.premium-calculation-configurator.index')->with('error', 'IC not found.');
        }

        foreach ($request->all() as $key => $value) {
            if (str_ends_with($key, '_type')) {
                $fieldKey = substr($key, 0, -5);
                $fieldType = $value;

                $fieldValue = null;
                $calculationType = 'na';

                $labelInstance = PremCalcLabel::where('label_key', $fieldKey)->first();
                $labelId = $labelInstance ? $labelInstance->id : null;

                $updateData = [
                    'label_id' => $labelId,
                    'ic_alias' => $icInstance->ic_alias,
                    'integration_type' => $icInstance->integration_type,
                    'segment' => $icInstance->segment,
                    'business_type' => $icInstance->business_type,
                    'updated_by' => auth()->user()->id,
                ];

                if ($fieldType == 'attribute_name') {
                    $attributeName = $request->input($fieldKey . '_attribute_name');

                    if (preg_match('/\((.*?)\)/', $attributeName, $matches)) {
                        $attributeTrail = $matches[1];

                        $attribute = PremCalcAttributes::where('attribute_trail', $attributeTrail)->first();

                        if ($attribute) {
                            $updateData['calculation_type'] = 'attribute';
                            $updateData['attribute_id'] = $attribute->id;
                            $updateData['formula_id'] = null;
                            $updateData['custom_val'] = null;
                        } else {
                            continue;
                        }
                    } else {
                        continue;
                    }
                } elseif ($fieldType == 'formula_name') {
                    $updateData['calculation_type'] = 'formula';
                    $formulaId = $request->input($fieldKey . '_formula_name');
                    $updateData['formula_id'] = $formulaId;
                    $updateData['attribute_id'] = null;
                    $updateData['custom_val'] = null;
                } elseif ($fieldType == 'custom_val') {
                    $updateData['calculation_type'] = 'custom_val';
                    $customVal = $request->input($fieldKey . '_custom_val');
                    $updateData['custom_val'] = $customVal;
                    $updateData['attribute_id'] = null;
                    $updateData['formula_id'] = null;
                } else {
                    $updateData['calculation_type'] = 'na';
                    $updateData['custom_val'] = null;
                    $updateData['attribute_id'] = null;
                    $updateData['formula_id'] = null;
                }

                $existingRow = PremCalcConfigurator::where('label_id', $labelId)
                    ->where('ic_alias', $icInstance->ic_alias)
                    ->where('segment', $icInstance->segment)
                    ->where('business_type', $icInstance->business_type)
                    ->first();

                if ($existingRow) {
                    $existingRow->update($updateData);
                } elseif (in_array($fieldType, ['attribute_name', 'formula_name', 'custom_val'])) {
                    $updateData['created_by'] = auth()->user()->id;
                    PremCalcConfigurator::create($updateData);
                }
            }
        }

        PremCalcActivation::updateOrCreate([
            'slug' => $ic
        ], [
            'is_active' => $request->activation == 'ENABLE',
            'created_by' => Auth::user()->id,
            'updated_by' => Auth::user()->id,
        ]);

        return redirect()->route('admin.ic-configuration.premium-calculation-configurator.index')->with('success', 'IC updated successfully.');
    }

    public function show($id)
    {
        if (!auth()->user()->can('premium_calculation_configurator.show')) {
            return response('unauthorized action', 401);
        }
        $ic = IcIntegrationType::where('slug', $id)->first();
        if (!$ic) {
            return response()->json(['error' => 'IC Integration Type not found'], 404);
        }

        $labels = PremCalcLabel::select([
            'prem_calc_labels.id',
            'prem_calc_labels.label_name',
            'prem_calc_labels.label_key',
            'prem_calc_labels.label_group',
            DB::raw("IFNULL(prem_calc_configurator.label_id, '') AS label_id"),
            DB::raw("IFNULL(prem_calc_configurator.ic_alias, '') AS ic_alias"),
            DB::raw("IFNULL(prem_calc_configurator.integration_type, '') AS integration_type"),
            DB::raw("IFNULL(prem_calc_configurator.segment, '') AS segment"),
            DB::raw("IFNULL(prem_calc_configurator.business_type, '') AS business_type"),
            DB::raw("IFNULL(prem_calc_configurator.calculation_type, 'na') AS calculation_type"),
            DB::raw("IFNULL(prem_calc_configurator.formula_id, '') AS formula_id"),
            DB::raw("IFNULL(prem_calc_configurator.attribute_id, '') AS attribute_id"),
            DB::raw("IFNULL(prem_calc_configurator.custom_val, '') AS custom_val"),
            DB::raw("IFNULL(prem_calc_formulas.formula_name, '') AS formula_name"), 
            DB::raw("IFNULL(prem_calc_attributes.attribute_name, '') AS attribute_name"), 
            DB::raw("IFNULL(prem_calc_attributes.attribute_trail, '') AS attribute_trail"), 
        ])
            ->leftJoin('prem_calc_configurator', function ($join) use ($ic) {
                $join->on('prem_calc_labels.id', '=', 'prem_calc_configurator.label_id')
                    ->whereNull('prem_calc_labels.deleted_at')
                    ->whereNull('prem_calc_configurator.deleted_at')
                    ->where('prem_calc_configurator.ic_alias', '=', $ic->ic_alias)
                    ->where('prem_calc_configurator.integration_type', '=', $ic->integration_type)
                    ->where('prem_calc_configurator.segment', '=', $ic->segment)
                    ->where('prem_calc_configurator.business_type', '=', $ic->business_type);
            })
            ->leftJoin('prem_calc_formulas', 'prem_calc_configurator.formula_id', '=', 'prem_calc_formulas.id')
            ->leftJoin('prem_calc_attributes', 'prem_calc_configurator.attribute_id', '=', 'prem_calc_attributes.id')
            ->groupBy(
                'prem_calc_labels.id',
                'prem_calc_labels.label_name',
                'prem_calc_labels.label_key',
                'prem_calc_labels.label_group',
                'prem_calc_configurator.label_id',
                'prem_calc_configurator.ic_alias',
                'prem_calc_configurator.integration_type',
                'prem_calc_configurator.segment',
                'prem_calc_configurator.business_type',
                'prem_calc_configurator.calculation_type',
                'prem_calc_configurator.formula_id',
                'prem_calc_configurator.attribute_id',
                'prem_calc_configurator.custom_val',
                'prem_calc_formulas.formula_name',
                'prem_calc_attributes.attribute_name',
                'prem_calc_attributes.attribute_trail'
            )
            ->orderByRaw('prem_calc_labels.label_group COLLATE UTF8MB4_UNICODE_CI')
            ->orderByRaw('prem_calc_labels.label_name COLLATE UTF8MB4_UNICODE_CI')
            ->get();

        $labelGroups = PremCalcLabel::distinct()->pluck('label_group');

        $results = [];
        foreach ($labels as $label) {
            if (in_array($label->label_group, $labelGroups->toArray())) {
                $results[$label->label_group][] = $label;
            }
        }

        return response()->json([
            'results' => $results,
            'labelGroups' => $labelGroups,
        ]);
    }

    public function export(Request $request, $id)
    {
        $id = base64_decode($id);
        $icConfigurator = IcIntegrationType::where('slug', $id)->firstOrFail();
        $existingConfig = PremCalcConfigurator::where('ic_alias', $icConfigurator->ic_alias)
        ->where('calculation_type', '!=', 'na')
        ->where('segment', $icConfigurator->segment)
        ->where('business_type', $icConfigurator->business_type)
        ->get();

            $jsonData = [];
            $lableIds = [];
            $formulaIds = [];
            $bucketIds = [];
            $placeHolderIds = [];
            $finalData = [
                'formulas' => [],
                'buckets' => [],
                'place-holders' => [],
                'labels' => []
            ];



            foreach ($existingConfig->toArray() as $value) {
                unset(
                    $value['id'],
                    $value['created_at'],
                    $value['updated_at'],
                    $value['created_by'],
                    $value['updated_by'],
                    $value['deleted_at'],
                );

                array_push($lableIds, $value['label_id']);
                if (!empty($value['formula_id'])) {
                    array_push($formulaIds, $value['formula_id']);

                    $formula = PremCalcFormula::find($value['formula_id']);
                    $extractedFormula = $formula->extract_formula;

                    $labelValues = \App\Http\Controllers\IcConfig\IcConfigurationController::getLabelData('label', $extractedFormula);
                    $lableIds = array_merge($lableIds, $labelValues);

                    $formulaValues = \App\Http\Controllers\IcConfig\IcConfigurationController::getLabelData('formula', $extractedFormula);
                    $formulaIds = array_merge($formulaIds, $formulaValues);

                    $bucketValues = \App\Http\Controllers\IcConfig\IcConfigurationController::getLabelData('bucket', $extractedFormula);
                    $bucketIds = array_merge($bucketIds, $bucketValues);

                    $placeHolderValues = \App\Http\Controllers\IcConfig\IcConfigurationController::getLabelData('place-holder', $extractedFormula);
                    $placeHolderIds = array_merge($placeHolderIds, $placeHolderValues);
                }
                array_push($jsonData, $value);
                
            }

            if (!empty($bucketIds)) {
                $finalData['buckets'] = PremCalcBucket::with([
                    'lists'
                ])->select('id', 'bucket_name', 'discount')
                ->whereIn('id', array_unique($bucketIds))
                ->get()
                ->toArray();

                $buckets = $finalData['buckets'];
                $labels = array_merge(
                    ...array_map(
                        fn($bucket) => array_column($bucket['lists'] ?? [], 'label_id'),
                        $buckets
                    )
                );

                $lableIds = array_merge($lableIds, $labels);
            }

            $finalData['config'] = $jsonData;
            if (!empty($lableIds)) {
                $finalData['labels'] = PremCalcLabel::select('id', 'label_name', 'label_key', 'label_group')
                ->whereIn('id', array_unique($lableIds))
                ->get()
                ->toArray();
            }

            if (!empty($formulaIds)) {
                $finalData['formulas'] = PremCalcFormula::select('id', 'formula_name', 'matrix')
                ->whereIn('id', array_unique($formulaIds))
                ->get()
                ->map(function ($formula) {
                    $data = $formula->toArray();
                    $data['full_formula'] = $formula->full_formula; // Add full_formula manually
                    return $data;
                })
                ->toArray();
            }


            if (!empty($bucketIds)) {
                $finalData['place-holders'] = PremCalcPlaceholder::select(
                    'id',
                    'placeholder_name',
                    'placeholder_key',
                    'placeholder_value',
                    'placeholder_type'
                )
                ->whereIn('id', array_unique($placeHolderIds))
                ->get()
                ->toArray();
            }
            $fileName = $icConfigurator->ic_alias.'_'.time().'.json';

            $jsonContent = json_encode($finalData);
            

            $tempFilePath = tempnam(sys_get_temp_dir(), 'json');
            file_put_contents($tempFilePath, $jsonContent);
        
            // Return response for downloading the file
            return response()->download($tempFilePath, $fileName)->deleteFileAfterSend(true);

    }

    public function import(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'jsonFile' => 'required|file|mimes:json',
                'override' => 'nullable|array|in:config,label,bucket'
            ]);

            if ($validator->fails()) {
                return response()->json(
                    [
                        'status' => false,
                        'message' => $validator->errors()->first()
                    ],
                    400
                );
            }

            $override = !empty($request->override) ? $request->override : [];

            $file = $request->file('jsonFile');
            $jsonContent = file_get_contents($file->getRealPath());
            $jsonContent = json_decode($jsonContent, true);

            if (empty($jsonContent['config'])) {
                return response()->json(
                    [
                        'status' => false,
                        'message' => 'Uploaded json file is empty'
                    ],
                    400
                );
            }

            $check = [
                'ic_alias' => $jsonContent['config'][0]['ic_alias'],
                'integration_type' => $jsonContent['config'][0]['integration_type'],
                'segment' => $jsonContent['config'][0]['segment'],
                'business_type' => $jsonContent['config'][0]['business_type'],
            ];

            //validate labels
            if (!empty($jsonContent['labels'])) {
                $labelList = PremCalcLabel::select('id', 'label_key')
                ->whereNull('deleted_at')
                ->whereIn('label_key', array_column($jsonContent['labels'], 'label_key'))
                ->get();
                foreach ($labelList as $label) {
                    $index =  array_search($label->label_key, array_column($jsonContent['labels'], 'label_key'));
                    $jsonContent['labels'][$index]['new_id'] = $label->id;
                }
            }

            //validate buckets
            if (!empty($jsonContent['buckets'])) {
                $bucketList = PremCalcBucket::with([
                    'lists',
                    'lists.label'
                ])->select('id', 'bucket_name', 'discount')
                ->whereNull('deleted_at')
                ->whereIn('bucket_name', array_column($jsonContent['buckets'], 'bucket_name'))
                ->get();
                foreach ($bucketList as $bucket) {
                    $index =  array_search($bucket->bucket_name, array_column($jsonContent['buckets'], 'bucket_name'));
                    if (!empty($jsonContent['buckets'][$index]['lists'])) {
                        foreach ($jsonContent['buckets'][$index]['lists'] as $lkey => $lval) {
                            $jsonContent['buckets'][$index]['lists'][$lkey]['new_bucket_id'] =  $bucket->id;
                        }
                    }
                    $jsonContent['buckets'][$index]['new_id'] = $bucket->id;
                }
            }

            //validate placeHolder
            if (!empty($jsonContent['place-holders'])) {
                $placeHolderList = PremCalcPlaceHolder::select('id', 'placeholder_key')
                ->whereNull('deleted_at')
                ->whereIn('placeholder_key', array_column($jsonContent['place-holders'], 'placeholder_key'))
                ->get();
                foreach ($placeHolderList as $placeHolder) {
                    $index =  array_search($placeHolder->placeholder_key, array_column($jsonContent['place-holders'], 'placeholder_key'));
                    $jsonContent['place-holders'][$index]['new_id'] = $placeHolder->id;
                }
            }

            DB::beginTransaction();

            //insert labels

            if (!empty($jsonContent['labels'])) {
                $labelInsert = [];
                foreach ($jsonContent['labels'] as $value) {
                    if (empty($value['new_id'])) {
                        unset($value['id']);
                        $value['created_at'] = date('Y-m-d H:i:s');
                        $value['updated_at'] = date('Y-m-d H:i:s');
                        $value['created_by'] = Auth::user()->id;
                        $value['updated_by'] = Auth::user()->id;
                        array_push($labelInsert, $value);
                    } elseif (in_array('label', $override)){
                        PremCalcLabel::updateOrCreate([
                            'label_key' => $value['label_key'],
                        ],[
                            'label_group' => $value['label_group'],
                            'label_name' => $value['label_name'],
                            'updated_by' => Auth::user()->id
                        ]);
                    }
                }
                if (!empty($labelInsert)) {
                    PremCalcLabel::insert($labelInsert);

                    $labelList = PremCalcLabel::select('id', 'label_key')
                    ->whereNull('deleted_at')
                    ->whereIn('label_key', array_column($labelInsert, 'label_key'))
                    ->get();
                    foreach ($labelList as $label) {
                        $index =  array_search($label->label_key, array_column($jsonContent['labels'], 'label_key'));
                        $jsonContent['labels'][$index]['new_id'] = $label->id;
                    }
                }
            }

            //insert buckets
            if (!empty($jsonContent['buckets'])) {
                $bucketInsert = [];
                foreach ($jsonContent['buckets'] as $value) {
                    if (empty($value['new_id'])) {
                        unset($value['id']);
                        $value['created_by'] = Auth::user()->id;
                        $value['updated_by'] = Auth::user()->id;

                        $lists = $value['lists'] ?? [];
                        unset($value['lists']);

                        $result = PremCalcBucket::create($value);

                        $listsInsert = [];
                        foreach ($lists as $l) {
                            unset($l['id']);
                            $l['prem_calc_bucket_id'] = $result->id;
                            $l['created_at'] = date('Y-m-d H:i:s');
                            $l['updated_at'] = date('Y-m-d H:i:s');
                            $l['created_by'] = Auth::user()->id;
                            $l['updated_by'] = Auth::user()->id;

                            array_push($listsInsert, $l);
                        }

                        if (!empty($listsInsert)) {
                            PremCalcBucketList::insert($listsInsert);
                        }
                        array_push($bucketInsert, $value);
                    } else {
                        if (in_array('bucket', $override)) {

                            PremCalcBucket::updateOrCreate([
                                'bucket_name' => $value['bucket_name'],
                            ], [
                                'discount' => $value['discount'],
                                'updated_by' => Auth::user()->id
                            ]);

                            PremCalcBucketList::where('id', $value['new_id'])
                            ->delete();

                            if (!empty($value['lists'])) {
                                $listInsert = [];
                                foreach ($value['lists'] as $lkey => $lval) {
                                    $labelId = $lval['label_id'];
                                    $index = array_search($labelId, array_column($jsonContent['labels'], 'id'));
                                    array_push($listInsert, [
                                        'prem_calc_bucket_id' => $value['new_id'],
                                        'label_id' => $jsonContent['labels'][$index]['new_id'],
                                        'type' => $lval['type'],
                                        'created_by' => Auth::user()->id,
                                        'updated_by' => Auth::user()->id,
                                        'created_at' => date('Y-m-d H:i:s'),
                                        'updated_at' => date('Y-m-d H:i:s')
                                    ]);
                                }
                                PremCalcBucketList::insert($listInsert);
                            }
                        }
                    }
                }
                if (!empty($bucketInsert)) {

                    $bucketList = PremCalcBucket::select('id', 'bucket_name')
                    ->whereNull('deleted_at')
                    ->whereIn('bucket_name', array_column($bucketInsert, 'bucket_name'))
                    ->get();
                    foreach ($bucketList as $bucket) {
                        $index =  array_search($bucket->bucket_name, array_column($jsonContent['buckets'], 'bucket_name'));
                        $jsonContent['buckets'][$index]['new_id'] = $bucket->id;

                        if (!empty($jsonContent['buckets'][$index]['lists'])) {
                            foreach ($jsonContent['buckets'][$index]['lists'] as $lkey => $lval) {
                                $jsonContent['buckets'][$index]['lists'][$lkey]['new_bucket_id'] =  $bucket->id;
                            }
                        }
                    }
                }
            }

            //insert place holders
            if (!empty($jsonContent['place-holders'])) {
                $placeHolderInsert = [];
                foreach ($jsonContent['place-holders'] as $value) {
                    if (empty($value['new_id'])) {
                        unset($value['id']);
                        $value['created_at'] = date('Y-m-d H:i:s');
                        $value['updated_at'] = date('Y-m-d H:i:s');
                        $value['created_by'] = Auth::user()->id;
                        $value['updated_by'] = Auth::user()->id;
                        array_push($placeHolderInsert, $value);
                    }
                }
                if (!empty($placeHolderInsert)) {
                    PremCalcPlaceHolder::insert($placeHolderInsert);

                    $placeHolderList = PremCalcPlaceHolder::select('id', 'placeholder_key')
                    ->whereNull('deleted_at')
                    ->whereIn('placeholder_key', array_column($placeHolderInsert, 'placeholder_key'))
                    ->get();
                    foreach ($placeHolderList as $placeHolder) {
                        $index =  array_search($placeHolder->placeholder_key, array_column($jsonContent['place-holders'], 'placeholder_key'));
                        $jsonContent['place-holders'][$index]['new_id'] = $placeHolder->id;
                    }
                }
            }

            if (!empty($jsonContent['formulas'])) {

                foreach ($jsonContent['formulas'] as $key => $formula) {
                    if (!empty($jsonContent['labels'])) {
                        foreach ($jsonContent['labels'] as $label) {
                            $old = '|L:' . $label['id'] . '|';
                            $new = '|L:' . $label['new_id'] . '|';

                            $jsonContent['formulas'][$key]['new_matrix'] = str_replace(
                                $old,
                                $new,
                                ($jsonContent['formulas'][$key]['new_matrix'] ?? $jsonContent['formulas'][$key]['matrix'])
                            );
                        }
                    }

                    if (!empty($jsonContent['buckets'])) {
                        foreach ($jsonContent['buckets'] as $bucket) {
                            if ($bucket['id'] == $bucket['new_id']) {
                                continue;
                            }
                            $old = '|B:' . $bucket['id'] . '|';
                            $new = '|B:' . $bucket['new_id'] . '|';

                            $jsonContent['formulas'][$key]['new_matrix'] = str_replace(
                                $old,
                                $new,
                                ($jsonContent['formulas'][$key]['new_matrix'] ?? $jsonContent['formulas'][$key]['matrix'])
                            );
                        }
                    }

                    if (!empty($jsonContent['place-holders'])) {
                        foreach ($jsonContent['place-holders'] as $ph) {
                            $old = '|PL:' . $ph['id'] . '|';
                            $new = '|PL:' . $ph['new_id'] . '|';

                            $jsonContent['formulas'][$key]['new_matrix'] = str_replace(
                                $old,
                                $new,
                                ($jsonContent['formulas'][$key]['new_matrix'] ?? $jsonContent['formulas'][$key]['matrix'])
                            );
                        }
                    }
                }
                $formulaList = PremCalcFormula::select('id', 'formula_name', 'matrix')
                    ->whereNull('deleted_at')
                    ->whereIn('formula_name', array_column($jsonContent['formulas'], 'formula_name'))
                    ->get();

                foreach ($formulaList as $formula) {
                    $index =  array_search($formula->formula_name, array_column($jsonContent['formulas'], 'formula_name'));
                    if (
                        $jsonContent['formulas'][$index]['new_matrix'] != $formula->matrix &&
                        $jsonContent['formulas'][$index]['full_formula'] != $formula->full_formula
                    ) {
                        DB::rollBack();

                        return response()->json([
                            'status' => false,
                            'message' => 'Formula ' . $formula->formula_name . ' is present but the formula matrix is different.'
                        ], 400);
                    }
                    $jsonContent['formulas'][$index]['new_id'] = $formula->id;
                }

                //check and insert here
                foreach ($jsonContent['formulas'] as $key => $formula) {
                    //first create formulas which doesnt have formulas inside it
                    if (empty($formula['new_id'])) {
                        $matrix = $formula['new_matrix'];

                        preg_match_all('/\|F:(\d+)\|/', $matrix, $matches);

                        if (empty($matches[1])) {
                            $result = PremCalcFormula::create([
                                'formula_name' => $formula['formula_name'],
                                'matrix' => $matrix,
                                'created_by' => Auth::user()->id,
                                'updated_by' => Auth::user()->id,
                            ]);

                            $jsonContent['formulas'][$key]['new_id'] = $result->id;
                        }
                    }
                }
                unset($formula);

               

                foreach ($jsonContent['formulas'] as &$formula) {
                    if (!empty($formula['new_id'])) {
                        $old = '|F:' . $formula['id'] . '|';
                        $new = '|F:' . $formula['new_id'] . '|';

                        // Replace in all formulas' new_matrix
                        array_walk($jsonContent['formulas'], function (&$f) use ($old, $new) {
                            $f['new_matrix'] = str_replace($old, $new, $f['new_matrix']);
                        });
                    }
                }
            
                $lastCreatedCount = null;
                do {
                    $lastCreatedCount = count(array_column($jsonContent['formulas'], 'new_id'));
                    self::createFormulaFromExport($jsonContent);
                    $formulaCount = count($jsonContent['formulas']);
                    $createdFormulaCount = count(array_column($jsonContent['formulas'], 'new_id'));
                    
                    $repeat = true;

                    if (
                        $createdFormulaCount == $formulaCount ||
                        $lastCreatedCount == $createdFormulaCount
                    ) {
                        $repeat = false;
                    }

                }while($repeat);

                unset($formula);
                foreach ($jsonContent['formulas'] as $key => $formula) {
                    if (!empty($jsonContent['formulas'][$key]['new_id'])) {
                        continue;
                    }
                    $matrix = $jsonContent['formulas'][$key]['new_matrix'];
                    preg_match_all('/\|F:(\d+)\|/', $matrix, $matches);
                    if (!empty($matches[1])) {
                        $recordExistsInDatabase = false;
                        foreach ($matches[1] as $keyId) {
                            $newIdList = array_column($jsonContent['formulas'], 'new_id');
                            if (in_array($keyId, $newIdList)) {
                                $recordExistsInDatabase = true;
                            } else {
                                $recordExistsInDatabase = false;
                                break;
                            }
                        }
                        if ($recordExistsInDatabase) {
                            $result = PremCalcFormula::create([
                                'formula_name' => $formula['formula_name'],
                                'matrix' => $matrix,
                                'created_by' => Auth::user()->id,
                                'updated_by' => Auth::user()->id,
                            ]);

                            $jsonContent['formulas'][$key]['new_id'] = $result->id;

                            $old = '|F:' . $formula['id'] . '|';
                            $new = '|F:' . $result->id . '|';

                            unset($f);
                            array_walk($jsonContent['formulas'], function (&$f) use ($old, $new) {
                                if (empty($f['new_id'])) {
                                    $f['new_matrix'] = str_replace($old, $new, $f['new_matrix']);
                                }
                            });

                        } else {
                            DB::rollBack();

                            return response()->json([
                                'status' => false,
                                'message' => 'Formula creation failed'
                            ], 500);
                        }
                    }
                }
            }

            foreach ($jsonContent['config'] as $key => $value) {
                $index = array_search($value['label_id'], array_column($jsonContent['labels'], 'id'));
                $newLabelId = $jsonContent['labels'][$index]['new_id'];

                $newFormulaId = null;
                if (!empty($value['formula_id'])) {
                    $index = array_search($value['formula_id'], array_column($jsonContent['formulas'], 'id'));
                    $newFormulaId = $jsonContent['formulas'][$index]['new_id'];
                }

                $check['label_id'] = $newLabelId;
                $exists = PremCalcConfigurator::where($check)
                ->where('calculation_type', '!=', 'na')
                ->exists();

                if (in_array('config', $override) || !$exists) {
                    PremCalcConfigurator::updateOrCreate($check, [
                        'calculation_type' => $value['calculation_type'],
                        'formula_id' => $newFormulaId,
                        // 'attribute_id' => $value['attribute_id'], this is pending
                        'custom_val' => $value['custom_val'],
                        'created_by' => Auth::user()->id,
                        'updated_by' => Auth::user()->id,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Configuration updated'
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error($th);

            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    public static function createFormulaFromExport(&$jsonContent)
    {
        foreach ($jsonContent['formulas'] as $key => $formula) {
            if (!empty($jsonContent['formulas'][$key]['new_id'])) {
                continue;
            }
            $matrix = $jsonContent['formulas'][$key]['new_matrix'];
            preg_match_all('/\|F:(\d+)\|/', $matrix, $matches);
            if (!empty($matches[1])) {
                $recordExistsInDatabase = false;
                foreach ($matches[1] as $keyId) {
                    $newIdList = array_column($jsonContent['formulas'], 'new_id');
                    $recordExistsInDatabase = in_array($keyId, $newIdList);
                }
                if ($recordExistsInDatabase) {
                    $result = PremCalcFormula::create([
                        'formula_name' => $formula['formula_name'],
                        'matrix' => $matrix,
                        'created_by' => Auth::user()->id,
                        'updated_by' => Auth::user()->id,
                    ]);

                    $jsonContent['formulas'][$key]['new_id'] = $result->id;

                    $old = '|F:' . $formula['id'] . '|';
                    $new = '|F:' . $result->id . '|';

                    unset($f);
                    array_walk($jsonContent['formulas'], function (&$f) use ($old, $new) {
                        if (empty($f['new_id'])) {
                            $f['new_matrix'] = str_replace($old, $new, $f['new_matrix']);
                        }
                    });

                }
            }
        }
    }
}
