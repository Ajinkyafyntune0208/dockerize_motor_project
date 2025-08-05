<?php

namespace App\Http\Controllers;

use App\Models\HidePyi;
use Illuminate\Http\Request;

class HidePyiController extends Controller
{
    public function index(Request $request)
    {
        $toggles = HidePyi::all()->keyBy('seller_type');
        return view('admin_lte.hide_pyi.index', compact('toggles'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'seller_type' => 'required',
            'status' => 'required'
        ]);

        HidePyi::updateOrCreate(
            ['seller_type' => $validated['seller_type']],
            ['status' => $validated['status']]
        );

        return redirect()->route('admin.hide_pyi')->with('success', 'Updated successfully!');
    }

    public static function getParentSellerType($sellerType)
    {
        $parentSellerType = null;

        $segregateSellerType = [
            'B2B' => [
                'P',
                'E',
                'PARTNER',
                'MISP'
            ],
            'B2C' => [
                'U',
                'B2C'
            ]
        ];

        $parentSellerType = collect($segregateSellerType)
            ->filter(fn($types) => in_array($sellerType, $types))
            ->keys()
            ->first();

        if (empty($sellerType)) {
            $parentSellerType = 'B2C';
        }
        return $parentSellerType;
    }
}
