<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TemplateModel;
use App\Models\EmailType;
use Illuminate\Support\Facades\Validator;

class TemplateMasterController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $templates = TemplateModel::orderBy('template_id','desc')->get();
        return view('template_master.index', compact('templates'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $alias = EmailType::all();
        $global_header = TemplateModel::where('alias', 'global_header')->latest()->First();
        $global_footer = TemplateModel::where('alias', 'global_footer')->latest()->First();
        return view('template_master.create', compact('alias','global_header','global_footer'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

        if(isset($request->status) && $request->status == 'on') {
            $request->status = 'on';
        } else {
            $request['status'] = 'off';
        }
        // 
        if(isset($request->communication_type_email)) {
            $request['communication_type'] =  $request->communication_type_email;
        }
        if(strtolower(trim($request['alias'])) == strtolower(trim('Global Header'))) {
            $request['alias'] = strtolower(trim('Global_Header'));
        }
        if(strtolower(trim($request['alias'])) == strtolower(trim('Global Footer'))) {
            $request['alias'] = strtolower(trim('Global_Footer'));
        }
        if(isset($request->get_name)){
            return ["data"=>explode(',', EmailType::where('value', $request->alias)->value('columns'))];
        } elseif(isset($request->option_name)){
           return $this->aliasAdd($request);
        }
        $rules = [
            'title'=>'required|string|max:150',
            'alias'=>'required|string|max:150',
            'communication_type'=>'required|in:email,sms,whatsapp',
            'content'=>'required',
            'status'=>'required'
        ];
        if($request->communication_type == "email") {
            if(strtolower(trim($request['alias'])) != strtolower(trim('Global_Header')) && strtolower(trim($request['alias'])) != strtolower(trim('Global_Footer'))) {
                $rules['subject'] = 'required';
                $rules['global_header'] = 'required|string|max:200';
                $rules['footer'] = 'required|string|max:200';
            } 
        } elseif($request->communication_type=="whatsapp") {
            $rules['message_type'] = 'required|string|max:200';
        }
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }
        if($request->alias == "global_header") {
            
            $global_header = TemplateModel::where('alias', 'global_header')->latest()->First();
            return $this->update($request,$global_header);
        } elseif($request->alias == "global_footer") {
            $global_footer = TemplateModel::where('alias', 'global_footer')->latest()->First();
            return $this->update($request,$global_footer);
        } else {
            TemplateModel::create([
                "title" => $request->title,
                "alias" => $request->alias,
                "communication_type" => $request->communication_type,
                "content" => $request->content,
                "to"=> $request->to,
                "cc"=> $request->cc,
                "bcc"=> $request->bcc,
                "subject"=> $request->subject,
                'footer' => $request->footer ?? null,
                'global_header' => $request->global_header ?? null,
                'message_type' => $request->message_type ?? null,
                "status" => $request->status=="on" ? 'Y' : 'N'
            ]);
            return redirect()->route('admin.template.index')->with([
                'status' => 'Template Added Successfully',
                'class' => 'success'
            ]);
        }
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
    public function edit(TemplateModel $template)
    {
      $alias = EmailType::all();
      $types = [['display_name'=>'Email', 'value'=>'email'],['display_name'=>'SMS', 'value'=>'sms'],['display_name'=>'Whatsapp', 'value'=>'whatsapp']];
      $autocomplete = EmailType::where('value',$template->alias)->value('columns');
      return view('template_master.edit', compact('template', 'alias', 'types', 'autocomplete'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, TemplateModel $template)
    {
        if(isset($request->status) && $request->status == 'on') {
            $request->status = 'on';
        } else {
            $request['status'] = 'off';
        }
        if(strtolower(trim($request['alias'])) == strtolower(trim('Global Header'))) {
            $request['alias'] = strtolower(trim('Global_Header'));
        }
        if(strtolower(trim($request['alias'])) == strtolower(trim('Global Footer'))) {
            $request['alias'] = strtolower(trim('Global_Footer'));
        }
        if(isset($request->communication_type_email)) {
            $request->communication_type =  $request->communication_type_email;
        }
        $rules = [
            'title'=>'required|string|max:150',
            'alias'=>'required|string|max:150',
            'communication_type'=>'required|in:email,sms,whatsapp',
            'content'=>'required'
        ];
        
        if($request->communication_type == "email"){
            if(strtolower(trim($request['alias'])) != strtolower(trim('Global_Header')) && strtolower(trim($request['alias'])) != strtolower(trim('Global_Footer'))) {
                $rules['subject'] = 'required';
                $rules['global_header'] = 'required|string|max:200';
                $rules['footer'] = 'required|string|max:200';
            }
        } elseif($request->communication_type=="whatsapp") {
            $rules['message_type'] = 'required|string|max:200';
        }
        $validator = Validator::make($request->all(), $rules);
          if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
          }
          $template = TemplateModel::where('template_id',$template->template_id)->first();
          $template->title = $request->title;
          $template->alias = $request->alias;
          $template->communication_type = $request->communication_type;
          $template->content = $request->content;
          $template->status = $request->status=="on" ? 'Y' : 'N';
          if(trim($request->communication_type) == 'email') {
            $template->to = $request->to ?? null;
            $template->cc = $request->cc ?? null;
            $template->bcc = $request->bcc ?? null;
            $template->subject = $request->subject ?? null;
            $template->footer = $request->footer ?? null;
            $template->global_header = $request->global_header ?? null;
            $template->message_type = null;
          } elseif(trim($request->communication_type) == 'whatsapp') {
            $template->message_type = $request->message_type;
            $template->to = null;
            $template->cc = null;
            $template->bcc = null;
            $template->subject =  null;
            $template->footer = null;
            $template->global_header =  null;
          } else {
            $template->to = null;
            $template->cc = null;
            $template->bcc = null;
            $template->subject =  null;
            $template->footer = null;
            $template->global_header =  null;
            $template->message_type = null;
          }
          $result = $template->save();
          if($result) {
            return redirect()->route('admin.template.index')->with([
                'status' => 'Template Updated Successfully',
                'class' => 'success'
              ]);
          } else {
            return back()->withErrors($validator)->withInput();
          }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(TemplateModel $template)
    {
        $template->delete();
        return redirect()->route('admin.template.index')->with([
            'status' => 'Template Deleted Successfully',
            'class' => 'success'
        ]);
    }

    public function deleteAlias(Request $request){
        EmailType::where('display_name',$request->alias)->delete();
        return [
            'status' => 'Alias Deleted Successfully',
            'class' => 'success',
            'alias' => $request->alias,
        ];

    }
    public function aliasAdd($request){
        $alias = str_replace(' ', '_', trim($request->option_name));
        $option_name = $request->option_edit ?? $request->option_name;
        $alias_count = EmailType::where('columns', $option_name)->count();
        if($alias_count == 0) {
            $add_alias = new EmailType;
            $add_alias->display_name = $request->option_name;
            $add_alias->value = $alias;
            $add_alias->columns = $request->option_name;
            $add_alias->save();
            return back()->with([
                'status' => 'Alias Added Successfully',
                'class' => 'success'
            ]);
        } else {
            $update = EmailType::where('columns', $option_name)->first();
            $update->display_name = $request->option_name;
            $update->value = $alias;
            $update->columns = $request->option_name;
            $update->save();
            return back()->with([
                'status' => 'Alias Updated Successfully',
                'class' => 'success'
            ]);
        }
    }
}