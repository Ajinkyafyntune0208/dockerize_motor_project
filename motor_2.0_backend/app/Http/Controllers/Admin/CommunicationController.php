<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TemplateModel;
use Mail;
use Validator;

class CommunicationController extends Controller
{
    public function index(Request $request){
        $rules = [
            "alias"=>"required",
            "type"=>"required|in:email,sms"
        ];
        if($request->type=="sms"){
            $rules['to'] = 'required|integer';
        }
        $template = TemplateModel::where(['alias'=>$request->alias, 'communication_type'=>$request->type])->first();
        if($template){
            if(!$template->to){
                $rules['to'] = 'required|email';
            }
            $internal = [];
            preg_match_all('~\B@\w+~', $template->content, $matches);
            foreach($matches[0] as $value){
                $value = str_replace('@', '', $value);
                if(!in_array($value, $internal))
                $rules[$value] = 'required';
            }
        }
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
          return response()->json(['status'=>500, 'errors'=>$validator->errors()],500);
        }
        if($request->type=="email"){
            return $this->sendMail($request->alias, $request->all());
        }else{
            return $this->sendSMS($request->to, $request->alias, $request->all());
        }
    }

    public function sendMail($alias, $data){
        $template = TemplateModel::where(['alias'=>$alias, 'communication_type'=>'email'])->first();
        $content = $template->content;
        foreach($data as $key=>$value){
            $content = str_replace("@".$key, $value, $content);
        }
        $to = $template->to ? $template->to : $data['to'];
        $bcc = $template->bcc ? explode(',', $template->bcc) : [];
        try{
            Mail::send('Email.common', ['title'=>$template->subject, 'content'=>$content], function($message) use ($to, $bcc, $template) {
                $message->to($to)->bcc($bcc)->subject($template->subject);
            });
        }catch(Exception $e){
            return $e->getMessage();
        }
        return response()->json(['status'=>200, 'message'=>'Email sent successfully']);
        
    }

    public function sendSMS($to, $alias, $data){
        $template = TemplateModel::where(['alias'=>$alias, 'communication_type'=>'sms'])->first();
        $content = $template->content;
        foreach($data as $key=>$value){
            $content = str_replace("@".$key, $value, $content);
        }
        return httpRequest('sms', ['send_to'=>$to, 'msg'=>strip_tags($content)]);
    }
}
