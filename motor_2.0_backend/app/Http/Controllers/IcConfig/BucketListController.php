<?php

namespace App\Http\Controllers\IcConfig;

use App\Http\Controllers\Controller;
use App\Models\PremCalcBucket;
use App\Models\PremCalcBucketList;
use App\Models\PremCalcFormula;
use App\Models\PremCalcLabel;
use App\Models\PremCalcPlaceholder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class BucketListController extends Controller
{
    public function index(Request $request)
    {
        if (!auth()->user()->can('buckets.list')) {
            return response('unauthorized action', 401);
        }
        if ($request->getMethod() == 'GET') {
            $bucketList = PremCalcBucket::select('id', 'bucket_name', 'discount', 'created_at')
            ->whereNull('deleted_at')
            ->orderBy('id', 'desc')
            ->paginate(15);

            return view('admin.ic-config.buckets.list', compact('bucketList'));
        }

        if (!auth()->user()->can('buckets.delete')) {
            return response('unauthorized action', 401);
        }

        $validator = Validator::make($request->all(), [
            'deleteId' => 'required',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator->errors());
        }

        $id = $request->deleteId;

        $bucketExists = PremCalcFormula::where('matrix', 'like', "%|B:$id|%")
        ->whereNull('deleted_at')
        ->exists();

        if ($bucketExists) {
            return redirect()->route('admin.ic-configuration.buckets.list')
            ->with([
                'status' => 'Bucket is already in use',
                'class' => 'danger',
            ]);
        }

        PremCalcBucket::find($id)
        ->update([
            'updated_by' => Auth::user()->id,
            'deleted_at' => now()
        ]);

        PremCalcBucketList::where('prem_calc_bucket_id', $id)
        ->update([
            'updated_by' => Auth::user()->id,
            'deleted_at' => now()
        ]);

        return redirect()->route('admin.ic-configuration.buckets.list')
        ->with([
            'status' => 'Bucket deleted successfully',
            'class' => 'success',
        ]);
    }

    public function create(Request $request)
    {
        if (!auth()->user()->can('buckets.create')) {
            return response('unauthorized action', 401);
        }
        $addons = PremCalcLabel::select('id', 'label_name')
            ->whereNull('deleted_at')
            ->where('label_group', 'Addons')
            ->get();

        if ($request->method() == 'GET') {
            $addons = PremCalcLabel::select('id', 'label_name')
            ->whereNull('deleted_at')
            ->where('label_group', 'Addons')
            ->get();

            return view('admin.ic-config.buckets.create', compact('addons'));
        } else {
            $addonIds = $addons->pluck('id')->toArray();
            $validator = Validator::make($request->all(), [
                'bucketName' => [
                    'required',
                    'regex:/^[A-Za-z0-9_]+$/',
                    Rule::unique('prem_calc_buckets', 'bucket_name')->whereNull('deleted_at'),
                ],
                'discount' => 'required|numeric|min:0|max:100',
                'mandatoryAddons' => 'nullable|array|in:'.implode(',', $addonIds),
                'optionalAddons' => 'nullable|array|in:'.implode(',', $addonIds),
                'excludedAddons' => 'nullable|array|in:'.implode(',', $addonIds),
            ]);

            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator->errors());
            }

            $mandatoryAddons = $request->mandatoryAddons ?? [];
            $optionalAddons = $request->optionalAddons ?? [];
            $excludedAddons = $request->excludedAddons ?? [];


            //check if labels present in any of the array
            if (
                array_intersect($mandatoryAddons, $optionalAddons) ||
                array_intersect($optionalAddons, $excludedAddons) ||
                array_intersect($excludedAddons, $mandatoryAddons)
            ) {
                return redirect()->back()->with([
                    'status' => "Invalid bucket list selection",
                    'class' => 'danger',
                ]);
            }

            $result = PremCalcBucket::create([
                'bucket_name' => $request->bucketName,
                'discount' => $request->discount,
                'created_by' => Auth::user()->id,
                'updated_by' => Auth::user()->id,
            ]);

            self::createPlaceHolder($result);

            $bucketList = [];
            foreach ($mandatoryAddons as $value) {
                array_push($bucketList, [
                    'prem_calc_bucket_id' => $result->id,
                    'label_id' => $value,
                    'type' => 'MANDATORY',
                    'created_by' => Auth::user()->id,
                    'updated_by' => Auth::user()->id,
                ]);
            }

            foreach ($optionalAddons as $value) {
                array_push($bucketList, [
                    'prem_calc_bucket_id' => $result->id,
                    'label_id' => $value,
                    'type' => 'OPTIONAL',
                    'created_by' => Auth::user()->id,
                    'updated_by' => Auth::user()->id,
                ]);
            }

            foreach ($excludedAddons as $value) {
                array_push($bucketList, [
                    'prem_calc_bucket_id' => $result->id,
                    'label_id' => $value,
                    'type' => 'EXCLUDED',
                    'created_by' => Auth::user()->id,
                    'updated_by' => Auth::user()->id,
                ]);
            }

            if (!empty($bucketList)) {
                PremCalcBucketList::insert($bucketList);
            }
            return redirect()->route('admin.ic-configuration.buckets.list')
            ->with([
                'status' => 'Bucket List created successfully',
                'class' => 'success',
            ]);
        }
    }

    public function edit(Request $request, $id)
    {
        if (!auth()->user()->can('buckets.edit')) {
            return response('unauthorized action', 401);
        }
        $bucket = PremCalcBucket::with([
            'lists',
        ])
        ->select('id', 'bucket_name', 'discount')
        ->whereNull('deleted_at')
        ->where('id', $id)
        ->first();

        if (empty($bucket)) {
            return redirect()->route('admin.ic-configuration.buckets.list')
            ->with([
                'status' => 'Bucket List not found',
                'class' => 'danger',
            ]);
        }

        $mandatoryAddonsSelected = [];
        $optionalAddonsSelected = [];
        $excludedAddonsSelected = [];

        if (!empty($bucket->lists->toArray())) {
            $mandatoryAddonsSelected = $bucket->lists->where('type', 'MANDATORY')->pluck('label_id')->toArray();
            $optionalAddonsSelected = $bucket->lists->where('type', 'OPTIONAL')->pluck('label_id')->toArray();
            $excludedAddonsSelected = $bucket->lists->where('type', 'EXCLUDED')->pluck('label_id')->toArray();
        }

        $addons = PremCalcLabel::select('id', 'label_name')
            ->whereNull('deleted_at')
            ->where('label_group', 'Addons')
            ->get();

        if ($request->method() == 'GET') {
            $addons = PremCalcLabel::select('id', 'label_name')
            ->whereNull('deleted_at')
            ->where('label_group', 'Addons')
            ->get();

            return view('admin.ic-config.buckets.edit', compact(
                'addons',
                'bucket',
                'mandatoryAddonsSelected',
                'optionalAddonsSelected',
                'excludedAddonsSelected'
            ));
        } else {
            $addonIds = $addons->pluck('id')->toArray();
            $validator = Validator::make($request->all(), [
                'bucketName' => [
                    'required',
                    'regex:/^[A-Za-z0-9_]+$/',
                    Rule::unique('prem_calc_buckets', 'bucket_name')
                    ->whereNull('deleted_at')
                    ->ignore($id),
                ],
                'discount' => 'required|numeric|min:0|max:100',
                'mandatoryAddons' => 'nullable|array|in:'.implode(',', $addonIds),
                'optionalAddons' => 'nullable|array|in:'.implode(',', $addonIds),
                'excludedAddons' => 'nullable|array|in:'.implode(',', $addonIds),
            ]);

            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator->errors());
            }

            $mandatoryAddons = $request->mandatoryAddons ?? [];
            $optionalAddons = $request->optionalAddons ?? [];
            $excludedAddons = $request->excludedAddons ?? [];


            //check if labels present in any of the array
            if (
                array_intersect($mandatoryAddons, $optionalAddons) ||
                array_intersect($optionalAddons, $excludedAddons) ||
                array_intersect($excludedAddons, $mandatoryAddons)
            ) {
                return redirect()->back()->with([
                    'status' => "Invalid bucket list selection",
                    'class' => 'danger',
                ]);
            }

            PremCalcBucket::find($id)
            ->update([
                'bucket_name' => $request->bucketName,
                'discount' => $request->discount,
                'updated_by' => Auth::user()->id,
            ]);

            self::editPlaceHolder(PremCalcBucket::find($id));

            PremCalcBucketList::where('prem_calc_bucket_id', $id)->delete();

            $bucketList = [];
            foreach ($mandatoryAddons as $value) {
                array_push($bucketList, [
                    'prem_calc_bucket_id' => $id,
                    'label_id' => $value,
                    'type' => 'MANDATORY',
                    'created_by' => Auth::user()->id,
                    'updated_by' => Auth::user()->id,
                ]);
            }

            foreach ($optionalAddons as $value) {
                array_push($bucketList, [
                    'prem_calc_bucket_id' => $id,
                    'label_id' => $value,
                    'type' => 'OPTIONAL',
                    'created_by' => Auth::user()->id,
                    'updated_by' => Auth::user()->id,
                ]);
            }

            foreach ($excludedAddons as $value) {
                array_push($bucketList, [
                    'prem_calc_bucket_id' => $id,
                    'label_id' => $value,
                    'type' => 'EXCLUDED',
                    'created_by' => Auth::user()->id,
                    'updated_by' => Auth::user()->id,
                ]);
            }

            if (!empty($bucketList)) {
                PremCalcBucketList::insert($bucketList);
            }
            return redirect()->route('admin.ic-configuration.buckets.list')
            ->with([
                'status' => 'Bucket List updated successfully',
                'class' => 'success',
            ]);
        }
    }

    public function view(Request $request, $id)
    {
        if (!auth()->user()->can('buckets.show')) {
            return response('unauthorized action', 401);
        }
        $bucket = PremCalcBucket::with([
            'lists',
        ])
        ->select('id', 'bucket_name', 'discount', 'created_at')
        ->whereNull('deleted_at')
        ->where('id', $id)
        ->first();

        if (empty($bucket)) {
            return response()->json([
                'status' => false
            ]);
        }

        $mandatoryAddonsSelected = [];
        $optionalAddonsSelected = [];
        $excludedAddonsSelected = [];

        if (!empty($bucket->lists->toArray())) {
            $mandatoryAddonsSelected = $bucket->lists->where('type', 'MANDATORY')->pluck('label.label_name')->toArray();
            $optionalAddonsSelected = $bucket->lists->where('type', 'OPTIONAL')->pluck('label.label_name')->toArray();
            $excludedAddonsSelected = $bucket->lists->where('type', 'EXCLUDED')->pluck('label.label_name')->toArray();
        }

        return response()->json([
            'status' => true,
            'data' => [
                'bucket' => $bucket,
                'mandatoryAddonsSelected' => $mandatoryAddonsSelected,
                'optionalAddonsSelected' => $optionalAddonsSelected,
                'excludedAddonsSelected' => $excludedAddonsSelected,
            ]
        ]);
    }

    public static function createPlaceHolder($bucket)
    {
        $placeHolderName = $bucket->bucket_name.'_discount_percent';

        $exists = PremCalcPlaceholder::where('placeholder_key', $placeHolderName)
        ->whereNull('deleted_at')
        ->exists();

        if ($exists) {
            return false;
        }

        $placeHolder = [
            'prem_calc_bucket_id' => $bucket->id,
            'placeholder_name' => $placeHolderName,
            'placeholder_key' => $placeHolderName,
            'placeholder_value' => '#',
            'placeholder_type' => 'system',
            'created_by' => Auth::user()->id,
            'updated_by' => Auth::user()->id,
        ];

        PremCalcPlaceholder::create($placeHolder);
    }

    public static function editPlaceHolder($bucket)
    {
        $placeHolderName = $bucket->bucket_name.'_discount_percent';

        $exists = PremCalcPlaceholder::where('placeholder_key', $placeHolderName)
        ->where('prem_calc_bucket_id', '!=', $bucket->id)
        ->whereNull('deleted_at')
        ->exists();

        if ($exists) {
            return false;
        }

        $placeHolder = [
            'placeholder_name' => $placeHolderName,
            'placeholder_key' => $placeHolderName,
            'placeholder_value' => '#',
            'placeholder_type' => 'system',
            'created_by' => Auth::user()->id,
            'updated_by' => Auth::user()->id,
        ];

        PremCalcPlaceholder::updateOrCreate([
            'prem_calc_bucket_id' => $bucket->id,
        ], $placeHolder);
    }
}
