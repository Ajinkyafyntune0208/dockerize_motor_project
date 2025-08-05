<?php

namespace App\Http\Controllers\IcConfig;

use App\Http\Controllers\Controller;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\IcIntegrationType;
use App\Models\IcVersionConfigurator;
use App\Models\PremCalcAttributes;
use App\Models\PremCalcBucket;
use App\Models\PremCalcConfigurator;
use Illuminate\Http\Request;

use App\Models\PremCalcFormula;
use App\Models\PremCalcLabel;
use App\Models\PremCalcPlaceholder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class IcConfigurationController extends Controller
{
    public function createFormula(Request $request)
    {
        if (!auth()->user()->can('formulas.create')) {
            return response('unauthorized action', 401);
        }
        $formulas = PremCalcFormula::whereNull('deleted_at')
        ->orderBy('formula_name')
            ->get();

        $buckets = PremCalcBucket::select('id', 'bucket_name')
        ->whereNull('deleted_at')
        ->orderBy('bucket_name')
        ->get();

        $placeHolders = PremCalcPlaceholder::select('id', 'placeholder_name')
        ->whereNull('deleted_at')
        ->orderBy('placeholder_name')
        ->get();

        $labels = PremCalcLabel::select('*')
        ->orderBy('label_group')
        ->get()
        ->groupBy('label_group')
        ->whereNull('deleted_at')
        ->toArray();

        $operators = self::operators();

        $labelGroups = array_keys($labels);
        foreach ($labelGroups as $key => $value) {
            $labelGroups[$key] = Str::snake($value);
        }

        foreach ($labels as $key => $items) {
            usort($items, function($a, $b) {
                return strcmp($a['label_name'], $b['label_name']);
            });
            $labels[$key] = $items;
        }

        if ($request->method() == 'GET') {
            return view('admin.ic-config.formula.create', compact(
                'labels',
                'operators',
                'labelGroups',
                'formulas',
                'buckets',
                'placeHolders'
            ));
        } else {
            $validator = Validator::make($request->all(), [
                'expressionName' => [
                    'required',
                    'regex:/^[A-Za-z0-9_]+$/',
                    Rule::unique('prem_calc_formulas', 'formula_name')->whereNull('deleted_at'),
                ],
                'formula' => 'required'
            ]);

            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator->errors());
            }

            $evalFormula = self::findFormula($request->formula, true);

            try {
                eval('$result = ' . $evalFormula . ';');
            } catch (\Throwable $e) {
                return redirect()->back()
                ->with([
                    'status' => 'Invalid Formula : ' .$e->getMessage(),
                    'class' => 'danger',
                ]);
            }

            PremCalcFormula::create([
                'formula_name' => $request->expressionName,
                'matrix' => $request->formula,
                'created_by' => Auth::user()->id,
                'updated_by' => Auth::user()->id,
            ]);

            return redirect()->route('admin.ic-configuration.formula.list-formula')->with([
                'status' => 'Formula Created successfully',
                'class' => 'success',
            ]);
        }
    }

    public function listFormula(Request $request)
    {
        if (!auth()->user()->can('formulas.list')) {
            return response('unauthorized action', 401);
        }
        if ($request->method() == 'GET') {
            $formulas = PremCalcFormula::whereNull('deleted_at')
                ->orderBy('id', 'desc')
                ->get();

            return view('admin.ic-config.formula.list', compact('formulas'));
        } else {

            if (!auth()->user()->can('formulas.delete')) {
                return response('unauthorized action', 401);
            }

            $validator = Validator::make($request->all(), [
                'deleteId' => 'required',
            ]);

            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator->errors());
            }

            $id = $request->deleteId;

            $formulaExists = PremCalcFormula::where('matrix', 'like', "%|F:$id|%")
            ->where('id', '!=', $id)
            ->whereNull('deleted_at')
            ->exists();

            if (!$formulaExists) {
                $formulaExists = PremCalcConfigurator::where('formula_id', $id)
                ->whereNull('deleted_at')
                ->exists();
            }

            if ($formulaExists) {
                return redirect()->route('admin.ic-configuration.formula.list-formula')
                ->with([
                    'status' => 'Formula is already in use',
                    'class' => 'danger',
                ]);
            }

            PremCalcFormula::find($id)
                ->update([
                    'updated_by' => Auth::user()->id,
                    'deleted_at' => now()
                ]);

            return redirect()->route('admin.ic-configuration.formula.list-formula')
                ->with([
                    'status' => 'Formula deleted successfully',
                    'class' => 'success',
                ]);
        }
    }

    public function editFormula(Request $request, $id)
    {
        if (!auth()->user()->can('formulas.edit')) {
            return response('unauthorized action', 401);
        }
        $formula = PremCalcFormula::whereNull('deleted_at')
            ->where('id', $id)
            ->first();

        if (empty($formula)) {
            return redirect()->route('admin.ic-configuration.formula.list-formula')
                ->with([
                    'status' => 'Formula not found',
                    'class' => 'danger',
                ]);
        }

        $formulas = PremCalcFormula::whereNull('deleted_at')
            ->whereNotIn('id', [$id])
            ->orderBy('formula_name')
            ->get();

        $buckets = PremCalcBucket::select('id', 'bucket_name')
        ->whereNull('deleted_at')
        ->orderBy('bucket_name')
        ->get();

        $placeHolders = PremCalcPlaceholder::select('id', 'placeholder_name')
        ->whereNull('deleted_at')
        ->orderBy('placeholder_name')
        ->get();

        $labels = PremCalcLabel::select('*')
        ->orderBy('label_group')
        ->get()
        ->groupBy('label_group')
        ->whereNull('deleted_at')
        ->toArray();

        $labelGroups = array_keys($labels);
        foreach ($labelGroups as $key => $value) {
            $labelGroups[$key] = Str::snake($value);
        }

        foreach ($labels as $key => $items) {
            usort($items, function($a, $b) {
                return strcmp($a['label_name'], $b['label_name']);
            });
            $labels[$key] = $items;
        }

        $operators = self::operators();

        $extractedFormula = $this->extractFormula($formula->matrix);

        $bucketValues = self::getLabelData('bucket', $extractedFormula);
        $selectedBuckets = PremCalcBucket::whereIn('id', $bucketValues)
        ->get();


        $placeHolderValues = self::getLabelData('place-holder', $extractedFormula);
        $selectedPlaceHolders = PremCalcPlaceholder::whereIn('id', $placeHolderValues)
        ->get();

        $labelValues = self::getLabelData('label', $extractedFormula);
        $selectedLabels = PremCalcLabel::whereIn('id', $labelValues)
            ->get();

        $formulaValues = self::getLabelData('formula', $extractedFormula);
        $selectedFormula = PremCalcFormula::whereIn('id', $formulaValues)
            ->get();

        if ($request->method() == 'GET') {
            return view('admin.ic-config.formula.edit', compact(
                'formula',
                'formulas',
                'labels',
                'operators',
                'labelGroups',
                'selectedLabels',
                'extractedFormula',
                'selectedFormula',
                'buckets',
                'placeHolders',
                'selectedPlaceHolders',
                'selectedBuckets'
            ));
        } else {

            $validator = Validator::make($request->all(), [
                'expressionName' => [
                    'required',
                    'regex:/^[A-Za-z0-9_]+$/',
                    Rule::unique('prem_calc_formulas', 'formula_name')
                    ->whereNull('deleted_at')
                    ->ignore($id),
                ],
                'formula' => 'required'
            ]);

            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator->errors());
            }

            $evalFormula = self::findFormula($request->formula, true);

            try {
                eval('$result = ' . $evalFormula . ';');
            } catch (\Throwable $e) {
                return redirect()->back()
                ->with([
                    'status' => "Invalid Formula : ".$e->getMessage(),
                    'class' => 'danger',
                ]);
            }

            PremCalcFormula::find($id)
                ->update([
                    'formula_name' => $request->expressionName,
                    'matrix' => $request->formula,
                    'updated_by' => Auth::user()->id,
                ]);

            return redirect()->route('admin.ic-configuration.formula.list-formula')
                ->with([
                    'status' => 'Formula updated successfully',
                    'class' => 'success',
                ]);
        }
    }

    public static function findFormula($string, $validate = false, $fullFormula = false, $onlyFormula = false)
    {
        preg_match('/\|IF:(.*?)\|/', $string, $ifMatches);
        $formulaStack = PremCalcFormula::select('matrix', 'id', 'formula_name')
        ->get()
        ->keyBy('id')
        ->toArray();
        
        $bucketStack = PremCalcBucket::pluck('bucket_name', 'id')
        ->toArray();
        $placeHolderStack = PremCalcPlaceholder::pluck('placeholder_name', 'id')
        ->toArray();

        if ($fullFormula) {
            $labelStack = PremCalcLabel::pluck('label_name', 'id');
        } else {
            $labelStack = PremCalcLabel::pluck('label_key', 'id');
        }
        $labelStack = $labelStack->toArray();

        if ($validate) {
            $unionIntersection = self::operators('unionIntersection');
            foreach ($unionIntersection as $key => $value) {
                $search = ' '.$value.' ';
                if (strpos($string, $search)) {
                    $string = str_replace($search, ' + ', $string);
                }
            }
        }

        preg_match_all('/\|(.*?)\|/', $string, $matches);
        foreach ($matches[1] as $key => $value) {
            preg_match('/[a-zA-Z]+/', $value, $output);
            if (isset($output[0])) {
                if (in_array($output[0], ['F'])) {
                    $id = preg_replace('/[^0-9]/', '', $value);
                    if (isset($formulaStack[$id]['matrix'])) {
                        $replacableString = '(' . $formulaStack[$id]['matrix'] . ')';
                        if ($onlyFormula) {
                            $replacableString = '(' . $formulaStack[$id]['formula_name'] . ')';
                        }
                        if ($validate) {
                            $replacableString = 1;
                        }
                        $string = str_replace($matches[0][$key], $replacableString, $string);
                        if (!$onlyFormula) {
                            return self::findFormula($string, $validate, $fullFormula, $onlyFormula);
                        }
                    }
                } elseif (in_array($output[0], ['L'])) {
                    $id = preg_replace('/[^0-9]/', '', $value);
                    if (isset($labelStack[$id])) {
                        $replacableString = $labelStack[$id];
                        if ($validate) {
                            $replacableString = 1;
                        }
                        $string = str_replace($matches[0][$key], $replacableString, $string);
                        return self::findFormula($string, $validate, $fullFormula, $onlyFormula);
                    }
                } elseif (in_array($output[0], ['PT'])) {
                    preg_match('/\|PT:(.*?)\|/', $matches[0][$key], $plainTextMatches);
                    $string = str_replace($matches[0][$key], $plainTextMatches[1], $string);
                    return self::findFormula($string, $validate, $fullFormula, $onlyFormula);
                } elseif (in_array($output[0], ['B'])) {
                    $id = preg_replace('/[^0-9]/', '', $value);
                    if (isset($bucketStack[$id])) {
                        $replacableString = $bucketStack[$id];
                        if ($validate) {
                            $replacableString = 1;
                        }
                        $string = str_replace($matches[0][$key], $replacableString, $string);
                        return self::findFormula($string, $validate, $fullFormula, $onlyFormula);
                    }
                } elseif (in_array($output[0], ['PL'])) {
                    $id = preg_replace('/[^0-9]/', '', $value);
                    if (isset($placeHolderStack[$id])) {
                        $replacableString = $placeHolderStack[$id];
                        if ($validate) {
                            $replacableString = 1;
                        }
                        $string = str_replace($matches[0][$key], $replacableString, $string);
                        return self::findFormula($string, $validate, $fullFormula, $onlyFormula);
                    }
                }
            }
        }

        $pattern = '/\[(?:IF|):\d+\]/';
        
        $string = preg_replace($pattern, '(', $string);

        $pattern = '/\[(?:ENDIF|):\d+\]/';
        $string = preg_replace($pattern, ')', $string);

        if ($validate) {
            $pattern = '/\[(?:ROUND|):\d+\]/';
            $string = preg_replace($pattern, '(', $string);

            $pattern = '/\[(?:ENDROUND|):\d+\]/';
            $string = preg_replace($pattern, ')', $string);

            $pattern = '/\[(?:CEIL|):\d+\]/';
            $string = preg_replace($pattern, '(', $string);

            $pattern = '/\[(?:ENDCEIL|):\d+\]/';
            $string = preg_replace($pattern, ')', $string);

            $pattern = '/\[(?:FLOOR|):\d+\]/';
            $string = preg_replace($pattern, '(', $string);

            $pattern = '/\[(?:ENDFLOOR|):\d+\]/';
            $string = preg_replace($pattern, ')', $string);
        } elseif ($fullFormula) {
            $pattern = '/\[(?:ROUND|):\d+\]/';
            $string = preg_replace($pattern, 'ROUND(', $string);

            $pattern = '/\[(?:ENDROUND|):\d+\]/';
            $string = preg_replace($pattern, ')', $string);

            $pattern = '/\[(?:CEIL|):\d+\]/';
            $string = preg_replace($pattern, 'CEIL(', $string);

            $pattern = '/\[(?:ENDCEIL|):\d+\]/';
            $string = preg_replace($pattern, ')', $string);

            $pattern = '/\[(?:FLOOR|):\d+\]/';
            $string = preg_replace($pattern, 'FLOOR(', $string);

            $pattern = '/\[(?:ENDFLOOR|):\d+\]/';
            $string = preg_replace($pattern, ')', $string);
        } else {
            $pattern = '/\[(?:ROUND|):\d+\]/';
            $string = preg_replace($pattern, '(', $string);

            $pattern = '/\[(?:ENDROUND|):\d+\]/';
            $string = preg_replace($pattern, ')~round', $string);

            $pattern = '/\[(?:CEIL|):\d+\]/';
            $string = preg_replace($pattern, '(', $string);

            $pattern = '/\[(?:ENDCEIL|):\d+\]/';
            $string = preg_replace($pattern, ')~ceil', $string);

            $pattern = '/\[(?:FLOOR|):\d+\]/';
            $string = preg_replace($pattern, '(', $string);

            $pattern = '/\[(?:ENDFLOOR|):\d+\]/';
            $string = preg_replace($pattern, ')~floor', $string);
        }

        return str_replace('(  ', '( ', $string);
    }

    public static function extractFormula($string)
    {
        $explodedArray = explode(' ', $string);

        // Step 2: Trim whitespace and filter out empty elements
        $resultArray = array_filter(array_map('trim', $explodedArray), function ($value) {
            return $value !== '';
        });
        $resultArray = array_values($resultArray);

        $finalOutput = [];
        $index = 0;

        while ($index < count($resultArray)) {
            $value = $resultArray[$index];

            preg_match('/[a-zA-Z]+/', $value, $output);

            if (isset($output[0])) {
                $type = '';
                $typeValue = '';

                if (in_array($output[0], self::operators('unionIntersection'))) {
                    $type = 'op';
                    $typeValue = $value;

                    $finalOutput[] = [
                        'type' => $type,
                        'value' => $typeValue
                    ];

                    $index++;
                    continue;
                }

                switch ($output[0]) {
                    case 'L':
                        $type = 'label';
                        $typeValue = preg_replace('/[^0-9]/', '', $value);
                        break;

                    case 'F':
                        $type = 'formula';
                        $typeValue = preg_replace('/[^0-9]/', '', $value);
                        break;
                    case 'B':
                        $type = 'bucket';
                        $typeValue = preg_replace('/[^0-9]/', '', $value);
                        break;
                    case 'PL':
                        $type = 'place-holder';
                        $typeValue = preg_replace('/[^0-9]/', '', $value);
                        break;

                    case 'PT':
                        $type = 'plain_text';
                        preg_match('/\|PT:(.*?)\|/', $value, $plainTextMatches);
                        $typeValue = $plainTextMatches[1];
                        break;

                    case 'IF':
                        $type = 'if';
                        preg_match('/:(.*?)\]/', $value, $ifMatches);
                        $ifId = $ifMatches[1];
                        $start = '[IF:' . $ifId . ']';
                        $end = '[ENDIF:' . $ifId . ']';

                        $startIndex = array_search($start, $resultArray);
                        $endIndex = array_search($end, $resultArray);

                        if ($startIndex !== false && $endIndex !== false) {
                            $conditionalBlock = array_slice($resultArray, $startIndex + 1, $endIndex - $startIndex - 1);
                            $conditionalBlockString = implode(' ', $conditionalBlock);
                            $typeValue = self::extractFormula($conditionalBlockString);

                            $index = $endIndex;
                        }
                        break;
                    case 'ROUND':
                        $type = 'round';
                        preg_match('/:(.*?)\]/', $value, $roundMatches);
                        $ifId = $roundMatches[1];
                        $start = '[ROUND:' . $ifId . ']';
                        $end = '[ENDROUND:' . $ifId . ']';

                        $startIndex = array_search($start, $resultArray);
                        $endIndex = array_search($end, $resultArray);

                        if ($startIndex !== false && $endIndex !== false) {
                            $conditionalBlock = array_slice($resultArray, $startIndex + 1, $endIndex - $startIndex - 1);
                            $conditionalBlockString = implode(' ', $conditionalBlock);
                            $typeValue = self::extractFormula($conditionalBlockString);

                            $index = $endIndex;
                        }
                        break;

                    case 'CEIL':
                        $type = 'ceil';
                        preg_match('/:(.*?)\]/', $value, $roundMatches);
                        $ifId = $roundMatches[1];
                        $start = '[CEIL:' . $ifId . ']';
                        $end = '[ENDCEIL:' . $ifId . ']';

                        $startIndex = array_search($start, $resultArray);
                        $endIndex = array_search($end, $resultArray);

                        if ($startIndex !== false && $endIndex !== false) {
                            $conditionalBlock = array_slice($resultArray, $startIndex + 1, $endIndex - $startIndex - 1);
                            $conditionalBlockString = implode(' ', $conditionalBlock);
                            $typeValue = self::extractFormula($conditionalBlockString);

                            $index = $endIndex;
                        }
                        break;
                    case 'FLOOR':
                        $type = 'floor';
                        preg_match('/:(.*?)\]/', $value, $roundMatches);
                        $ifId = $roundMatches[1];
                        $start = '[FLOOR:' . $ifId . ']';
                        $end = '[ENDFLOOR:' . $ifId . ']';

                        $startIndex = array_search($start, $resultArray);
                        $endIndex = array_search($end, $resultArray);

                        if ($startIndex !== false && $endIndex !== false) {
                            $conditionalBlock = array_slice($resultArray, $startIndex + 1, $endIndex - $startIndex - 1);
                            $conditionalBlockString = implode(' ', $conditionalBlock);
                            $typeValue = self::extractFormula($conditionalBlockString);

                            $index = $endIndex;
                        }
                        break;
                }

                if ($type) {
                    $finalOutput[] = [
                        'type' => $type,
                        'value' => $typeValue
                    ];
                }
            } else {
                $finalOutput[] = [
                    'type' => 'op',
                    'value' => $value
                ];
            }

            $index++;
        }

        return $finalOutput;
    }


    public function viewFormula(Request $request, $id)
    {
        if (!auth()->user()->can('formulas.show')) {
            return response('unauthorized action', 401);
        }
        $formula = PremCalcFormula::whereNull('deleted_at')
            ->where('id', $id)
            ->first();

        if (empty($formula)) {
            return response()->json([
                'status' => false
            ]);
        }

        $formulaUsed = PremCalcFormula::whereNull('deleted_at')
        ->where('matrix', 'like', "%|F:$id|%")
        ->where('id', '!=', $id)
        ->pluck('formula_name')
        ->toArray();

        $configList = PremCalcConfigurator::select('ic_alias', 'integration_type', 'segment', 'business_type', 'label_key')
        ->where('calculation_type', '!=', 'na')
        ->join('prem_calc_labels as l', 'l.id', '=', 'prem_calc_configurator.label_id')
        ->where('formula_id', $id)
        ->get()
        ->toArray();

        $responseData = [
            'status' => true,
            'data' => [
                'name' => $formula->formula_name,
                'matrix' => $formula->matrix,
                'formula' => $formula->full_formula_with_label,
                'full_formula' => $formula->full_formula,
                'short_formula' => $formula->short_formula,
            ]
        ];

        if (!empty($formulaUsed)) {
            $responseData['data']['used'] = $formulaUsed;
        }

        if (!empty($configList)) {
            $responseData['data']['config'] = $configList;
        }
        return response()->json($responseData);
    }

    public static function operators($type = null)
    {
        $logical = [
            'AND' => '&&',
            'OR' => '||',
            'NOT' => '!',
        ];

        $arithmetic = [
            '+' => '+',
            '-' => '-',
            '×' => '*',
            '÷' => '/',
        ];

        $comparision = [
            '<' => '<',
            '>' => '>',
            '=' => '==',
            '≠' => '!=',
            '≤' => '<=',
            '≥' => '>=',
        ];

        $others = [
            '(' => '(',
            ')' => ')'
        ];

        $union = [
            '∪' => 'union'
        ];

        $intersection = [
            '∩' => 'intersection'
        ];

        $unionIntersection = array_merge($union, $intersection);

        if (!empty($type) && isset($$type)) {
            return $$type;
        }

        return array_merge(
            $arithmetic,
            $comparision,
            $logical,
            $others,
            $unionIntersection,
        );
    }


    public static function tokenizedFormula($matrix)
    {
        $expression = self::findFormula($matrix);

        $tokens = [];
        $length = strlen($expression);
        $operators = array_values(self::operators());

        $multiCharOperators = self::operators('comparision');
        $multiCharOperators = array_merge($multiCharOperators, self::operators('logical'));
        $multiCharOperators = array_values($multiCharOperators);

        $token = '';
        for ($i = 0; $i < $length; $i++) {
            $char = $expression[$i];

            if ($i < $length - 1 && !ctype_space($expression[$i + 1])) {
                $twoCharOperator = $char . $expression[$i + 1];

                if (in_array($twoCharOperator, $multiCharOperators)) {
                    if ($token !== '') {
                        $tokens[] = $token;
                        $token = '';
                    }
                    $tokens[] = $twoCharOperator;
                    $i++; // Skip the next character
                    continue;
                }
            }

            if (!ctype_space($char)) {
                if (in_array($char, $operators)) {
                    if ($token !== '') {
                        $tokens[] = $token;
                        $token = '';
                    }
                    $tokens[] = $char;
                } elseif (ctype_alnum($char) || $char === '_') {
                    $token .= $char;
                } else {
                    if ($token !== '') {
                        $tokens[] = $token;
                        $token = '';
                    }
                }
            }
        }
        if ($token !== '') {
            $tokens[] = $token;
        }

        $tokens = array_values($tokens);
        
        $tokens = array_map(function($item) {
            return "'".$item."'";
        }, $tokens);

        return implode(',', $tokens);
    }

    public static function getLabelData($type, $array)
    {
        $idList = [];
        foreach ($array as $value) {
            if (is_array($value['value'] ?? '')) {
                $idList = array_merge($idList, self::getLabelData($type, $value['value']));
            } elseif (($value['type'] ?? '') == $type) {
                array_push($idList, $value['value']);
            }
        }

        return array_unique($idList);
    }

    public static function getFormulas($productData, &$data, $request)
    {
        if ($data['status'] ?? false) {
            $enquiryId = customDecrypt($request->enquiryId);
            $corporateData = CorporateVehiclesQuotesRequest::where('user_product_journey_id', $enquiryId)
            ->first();

            if ($request->is_renewal == 'Y' && $corporateData->is_renewal == 'Y') {
                $businessType = 'renewal';
            } else {
                $businessType = 'rollover';
                if (!empty($corporateData->business_type)) {
                    $businessType = $corporateData->business_type;
                }
            }

            $productType = 'reg';
            
            if (in_array(
                $productData->premium_type_code,
                ['short_term_6', 'short_term_6_breakin', 'short_term_3', 'short_term_3_breakin', 'breakin']
            )) {
                if (in_array($productData->premium_type_code, ['short_term_6', 'short_term_6_breakin'])) {
                    $productType = 'st6';
                }

                if (in_array($productData->premium_type_code, ['short_term_3', 'short_term_3_breakin'])) {
                    $productType = 'st3';
                }
                $businessType = $businessType . '.' . 'comprehensive';
            } elseif (in_array($productData->premium_type_code, ['third_party_breakin', 'own_damage_breakin'])) {
                $businessType = $businessType . '.' . str_replace('_breakin', '', $productData->premium_type_code);
            } else {
                $businessType = $businessType . '.' . $productData->premium_type_code;
            }

            if ($productData->good_driver_discount == 'Yes') {
                $productType = 'gdd';
            }
            $integrationType = $productType;

            $segment = get_parent_code($productData->product_sub_type_id);

            $versions = IcVersionConfigurator::where([
                'ic_alias' => $productData->company_alias,
                'segment' => $segment,
                'business_type' => $businessType,
                'integration_type' => $integrationType
            ])
            ->select('ic_version_configurators.version', 'ic_version_configurators.kit_type')
            ->join('ic_version_activations as ac', 'ac.slug', '=', 'ic_version_configurators.slug')
            ->where('ac.is_active', 1)
            ->first();

            $integrationTypeList = [];

            if (
                !empty($versions)
            ) {
                $kit_type = $versions->kit_type;

                //convert xml:soap to xml
                if (strpos($kit_type, ':') !== false) {
                    $kit_type = explode(':', $kit_type);
                    $kit_type = $kit_type[0];
                }
                $type = $integrationType.".".$versions->version.".".$kit_type;
                array_push($integrationTypeList, $type);
                if ($versions->version == 'v1' && $kit_type == 'json') {
                    array_push(
                        $integrationTypeList,
                        $integrationType,
                        $integrationType . '.v1',
                        $integrationType . '.v1.json'
                    );
                }
            } else {
                array_push(
                    $integrationTypeList,
                    $integrationType,
                    $integrationType . '.v1',
                    $integrationType . '.v1.json'
                );
            }

            $queryData = [
                'business_type' =>  $businessType,
                'segment' => strtolower($segment),
                'ic_alias' => $productData->company_alias
            ];

            $slugList = [];
            foreach($integrationTypeList as $int) {
                $slug = $productData->company_alias.'.'.$int.'.'.strtolower($segment).'.'.$businessType;
                array_push($slugList, $slug);
            }

            $icIntegrationType = IcIntegrationType::with('activation')
            ->whereIn('slug', $slugList)
            ->first();

            if (!$icIntegrationType?->activation?->is_active) {
                return;
            }

            $premiumConfig = PremCalcConfigurator::with([
                'getFormula',
                // 'getIcAttribute',
                'getLabel'
            ])
                ->whereNull('deleted_at')
                ->where('calculation_type', '!=', 'na')
                ->where($queryData)
                ->whereIn('integration_type', $integrationTypeList)
                ->get();

            $allBuckets = PremCalcBucket::select('bucket_name', 'id')
            ->whereNull('deleted_at')
            ->get()
            ->toArray();

            if (count($premiumConfig) > 0) {
                $configData = [];
                $allBucketIds = [];
                foreach ($premiumConfig as $value) {
                    $labelData = $value->getLabel;
                    if (!empty($labelData)) {
                        switch ($value->calculation_type) {
                            case 'attribute':
                                //logic is pending..this will be taken in next phase
                                // $configData[$labelData->label_key] = $value->getIcAttribute?->attribute_trail;
                                break;

                            case 'formula':
                                $formula = $value->getFormula;
                                $fullFormula = null;

                                if (!empty($formula)) {
                                    $fullFormula = $formula?->full_formula;

                                    //get bucket ids  here
                                    $matchingBuckets = array_column(
                                        array_filter($allBuckets, function ($bucket) use ($fullFormula) {
                                            return strpos($fullFormula, $bucket['bucket_name']) !== false;
                                        }),
                                        'id'
                                    );

                                    if (!empty($matchingBuckets)) {
                                        $allBucketIds = array_merge($allBucketIds, $matchingBuckets);
                                    }
                                }

                                $configData[$labelData->label_key] = $fullFormula;
                                
                                break;
                            case 'custom_val':
                                $configData[$labelData->label_key] = $value->custom_val;
                                break;
                            default:
                                Log::error("Invalid calculation type in IC Premium config : " . $value);
                                break;
                        }
                    }
                }

                if (!empty($allBucketIds)) {
                    $allBucketIds = array_unique($allBucketIds);
                    $allBuckets = PremCalcBucket::with(['lists', 'lists.label'])
                    ->whereIn('id', $allBucketIds)
                        ->whereNull('deleted_at')
                        ->get()
                        ->reduce(function ($carry, $bucket) {
                            $carry[$bucket->bucket_name] = [
                                'discount' => $bucket->discount,
                                'addons' => $bucket->lists->map(function ($item) {
                                    return [
                                        'type' => $item->type,
                                        'addon' => $item->label->label_key,
                                    ];
                                })->toArray(),
                            ];
                            return $carry;
                        }, []);

                    if (!empty($allBuckets)) {
                        $data['data']['buckets'] = $allBuckets;
                    }
                }
                $data['data']['premCalc'] = $configData;
            }
        }
    }

    public function checkFormula(Request $request)
    {
        $matrix = $request->matrix;

        $formula = PremCalcFormula::whereNull('deleted_at')
        ->select('formula_name')
        ->where('matrix', $matrix)
        ->when(!empty($request->formulaId), function ($query) use ($request) {
            $query->where('id', '!=', $request->formulaId);
        })
        ->first();

        return response()->json([
            'status' => true,
            'formulaExists' => !empty($formula),
            'formulaName' => $formula?->formula_name
        ]);
    }


    public static function extractIcAttributes($data)
    {
        try {
            $policyType = explode('.', $data['business_type'])[1];
            $businessType = explode('.', $data['business_type'])[0];
            $apiRequest = [
                'policy_type' => $policyType,
                'business_type' => $businessType,
                'company' => $data['ic_alias'],
                'section' => $data['segment']
            ];

            $url = config('constants.brokerConstant.IC_SAMPLING_ENDPOINT');

            if (empty($url)) {
                return false;
            }

            $response = Http::post($url, $apiRequest);

            $response = $response->json();

            if (($response['status'] ?? false) && !empty($response['hierarchy'])) {

                $hierarchy = $response['hierarchy'];

                foreach ($hierarchy as $key => $value) {

                    $keyPath = explode('.', $key);
                    $key_path_count = count($keyPath);
                    $leafNode = $keyPath[$key_path_count - 1];
                    $leafNodeType = $value;
                    $leaf_node_val = &$response['section'];
                    foreach ($keyPath as $pathKey) {
                        $leaf_node_val = &$leaf_node_val[$pathKey];
                    }
                    $leafNodeValue = $leaf_node_val;

                    if (!is_array($leafNodeValue) || (is_array($leafNodeValue) && !empty($leafNodeValue))) {
                        if (in_array(strtolower($leafNodeType), ['array', 'boolean', 'bool', 'null'])) {
                            $leafNodeValue = var_export($leafNodeValue, true);
                        }

                        PremCalcAttributes::updateOrCreate(
                            [
                                'ic_alias' => $data['ic_alias'],
                                'segment' => $data['segment'],
                                'integration_type' => $data['integration_type'],
                                'business_type' => $data['business_type'],
                                'attribute_trail' => $key,
                                'attribute_name' => $leafNode
                            ],
                            [
                                'sample_value' => $leafNodeValue,
                                'sample_type' => $leafNodeType,
                                'created_by' => 0,
                                'updated_by' => 0,
                            ]
                        );
                    }
                }
            }
        } catch (\Throwable $th) {
            Log::error($th);
        }
    }
}
