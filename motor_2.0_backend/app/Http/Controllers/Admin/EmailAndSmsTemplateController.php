<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailSmsTemplate;
use Illuminate\Http\Request;

class EmailAndSmsTemplateController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $email_sms_templates = EmailSmsTemplate::paginate();
        return view('admin.emailTepmlate.index', compact('email_sms_templates'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('admin.emailTepmlate.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validateData = $request->validate([
            'email_sms_name' => ['required', 'string', 'unique:\App\Models\EmailSmsTemplate,email_sms_name'],
            'type' => ['required', 'string'],
            'subject' => ['required', 'string'],
            'body' => ['required', 'string'],
            'variable' => ['required', 'string'],
            'status' => ['required', 'string'],
        ]);

        try {
            $validateData['variable'] = explode(',', $validateData['variable']);
            EmailSmsTemplate::create($validateData);
            return redirect()->route('admin.email-sms-template.index')->with([
                'status' => 'Email SMS Template Created Successfully..!',
                'class' => 'success',
            ]);
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
     * @param  \App\Models\EmailSmsTemplate  $emailSmsTemplate
     * @return \Illuminate\Http\Response
     */
    public function show(EmailSmsTemplate $emailSmsTemplate)
    {
        return view('admin.emailTepmlate.show', compact('emailSmsTemplate'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\EmailSmsTemplate  $emailSmsTemplate
     * @return \Illuminate\Http\Response
     */
    public function edit(EmailSmsTemplate $emailSmsTemplate)
    {
        return view('admin.emailTepmlate.edit', compact('emailSmsTemplate'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\EmailSmsTemplate  $emailSmsTemplate
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, EmailSmsTemplate $emailSmsTemplate)
    {
        $validateData = $request->validate([
            'email_sms_name' => ['nullable', 'string', 'unique:\App\Models\EmailSmsTemplate,email_sms_name,'. $emailSmsTemplate->id],
            'type' => ['nullable', 'string'],
            'subject' => ['nullable', 'string'],
            'variable' => ['required', 'string'],
            'body' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
        ]);

        try {
            $validateData['variable'] = explode(',', $validateData['variable']);
            
            $emailSmsTemplate->update($validateData);
            return redirect()->route('admin.email-sms-template.index')->with([
                'status' => 'Email SMS Template Updated Successfully..!',
                'class' => 'success',
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with([
                'status' => 'Something wents wrong' . $e->getMessage() . '...!',
                'class' => 'danger',
            ]);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\EmailSmsTemplate  $emailSmsTemplate
     * @return \Illuminate\Http\Response
     */
    public function destroy(EmailSmsTemplate $emailSmsTemplate)
    {
        try {
            $emailSmsTemplate->delete();
            return redirect()->route('admin.email-sms-template.index')->with([
                'status' => 'Email SMS Template Deleted Successfully..!',
                'class' => 'success',
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with([
                'status' => 'Something wents wrong' . $e->getMessage() . '...!',
                'class' => 'danger',
            ]);
        }
    }
}
