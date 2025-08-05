<?php

namespace App\Http\Controllers\Lte\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PasswordPolicy;

class PasswordPolicyController extends Controller
{

    public function index()
    {
        if (!auth()->user()->can('password_policy.list')) {
            abort(403, 'Unauthorized action.');
        }
        $data = PasswordPolicy::select('label', 'key', 'value')->get()->pluck('value', 'key');
        return view('admin_lte.password_policy.index', compact('data'));
    }

    public function create()
    {
        //
    }

    public function store(Request $request)
    {
        foreach ($request->all() as $k => $v) {
            $k = str_replace('_', '.', $k);
            $userData = [
                'key' => $k,
                'value' => $v
            ];

            $data = PasswordPolicy::updateOrCreate(
                [
                    'key' => $k,
                ],[
                    'value' => $v
                ]
            );
            if (!$data) {
                return redirect()->back()->with([
                    'status' => 'Something went wrong',
                    'class' => 'danger'
                ]);
            }
        }
        return redirect()->back()->with([
            'status' => 'updated',
            'class' => 'success'
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\PasswordPolicy  $passwordPolicy
     * @return \Illuminate\Http\Response
     */
    public function show(PasswordPolicy $passwordPolicy)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\PasswordPolicy  $passwordPolicy
     * @return \Illuminate\Http\Response
     */
    public function edit(PasswordPolicy $passwordPolicy)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\PasswordPolicy  $passwordPolicy
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, PasswordPolicy $passwordPolicy)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\PasswordPolicy  $passwordPolicy
     * @return \Illuminate\Http\Response
     */
    public function destroy(PasswordPolicy $passwordPolicy)
    {
        //
    }
}
