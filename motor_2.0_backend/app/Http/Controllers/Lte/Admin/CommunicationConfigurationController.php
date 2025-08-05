<?php

namespace App\Http\Controllers\Lte\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommunicationConfiguration;
use Illuminate\Http\Request;

class CommunicationConfigurationController extends Controller
{

    public function index()
    {

    $pageNames = self::getPageNames();


      $pages = [];
      foreach ($pageNames as $pageName) {
        $pages[$pageName] = [
          'email' => false,
          'sms' => false,
          'whatsapp_api' => false,
          'whatsapp_redirection' => false,
          'email_is_enable' => true,
          'sms_is_enable' => true,
          'whatsapp_api_is_enable' => true,
          'whatsapp_redirection_is_enable' => true,
          'all_btn' => false
        ];
      }

      $existingConfigurations = CommunicationConfiguration::all();

      foreach ($existingConfigurations as $config) {
        $pageName = $config->page_name;
        $pages[$pageName] = [
          'email' => $config->email,
          'sms' => $config->sms,
          'whatsapp_api' => $config->whatsapp_api,
          'whatsapp_redirection' => $config->whatsapp_redirection,
          'email_is_enable' => $config->email_is_enable,
          'sms_is_enable' => $config->sms_is_enable,
          'whatsapp_api_is_enable' => $config->whatsapp_api_is_enable,
          'whatsapp_redirection_is_enable' => $config->whatsapp_redirection_is_enable,
          'all_btn' => $config->all_btn
        ];
      }

      return view('admin_lte.communicationconfiguration.index', compact('pages'));
    }

  public function store(Request $request)
  {
      $pageNames = self::getPageNames();
      
      $dataProcessed = false; 
  
      foreach ($pageNames as $pageName) {
        
          $slug = strtolower(str_replace(' ', '_', $pageName));

          $email = $request->has("{$pageName}_email") ? 1 : 0;
          $sms = $request->has("{$pageName}_sms") ? 1 : 0;
          $whatsappApi = $request->has("{$pageName}_whatsapp_api") ? 1 : 0;
          $whatsappRedirection = $request->has("{$pageName}_whatsapp_redirection") ? 1 : 0;
          $allBtn = $request->has("{$pageName}_all_btn") ? 1 : 0;
  
        
          $data = [
              'page_name' => $pageName, 
              'slug' => $slug,
              'email' => $email,
              'sms' => $sms,
              'whatsapp_api' => $whatsappApi,
              'whatsapp_redirection' => $whatsappRedirection,
              'all_btn' => $allBtn
          ];
  
     
          if ($email == 0 && $sms == 0 && $whatsappApi == 0 && $whatsappRedirection == 0) {
             
              $dataProcessed = true; 
              $existingConfig = CommunicationConfiguration::where('page_name', $pageName)->first();  // Match with the original pageName
              if ($existingConfig) {
               
                  $existingConfig->update($data);
              } else {
                
                  CommunicationConfiguration::create($data);
              }
          } else {
             
              $dataProcessed = true; 
              $existingConfig = CommunicationConfiguration::where('page_name', $pageName)->first();  // Match with the original pageName
              if ($existingConfig) {
                 
                  $existingConfig->update($data);
              } else {
               
                  CommunicationConfiguration::create($data);
              }
          }
      }
  
      if ($dataProcessed) {
          return redirect()->route('admin.communication-configuration.index')->with('success', 'Configuration saved successfully.');
      } else {
          return redirect()->route('admin.communication-configuration.index')->with('error', 'No valid data to save.');
      }
  }
  

    public function getBrokerThemeData()
    {
      $pageNames = self::getPageNames();

      $pagesAdditionalAddons = CommunicationConfiguration::distinct('page_name')->pluck('page_name')->toArray();

      $pageNames = array_unique(array_merge($pageNames, $pagesAdditionalAddons));

      $pages = [];
      foreach ($pageNames as $pageName) {
        $slug = strtolower(str_replace(' ', '_', $pageName));
        $pages[$slug] = [
          'page_name' => $pageName,
          'email_is_enable' => false,
          'email' => false,
          'sms_is_enable' => false,
          'sms' => false,
          'whatsapp_api_is_enable' => false,
          'whatsapp_api' => false,
          'whatsapp_redirection_is_enable' => false,
          'whatsapp_redirection' => false,
          'all_btn' => false
        ];
      }

      $existingConfigurations = CommunicationConfiguration::whereIn('page_name', $pageNames)->get();

      foreach ($existingConfigurations as $config) {
        $slug = strtolower(str_replace(' ', '_', $config->page_name));
        $pages[$slug] = [
          'page_name' => $config->page_name,
          'email_is_enable' => $config->email_is_enable ? true : false,
          'email' => $config->email ? true : false,
          'sms_is_enable' => $config->sms_is_enable ? true : false,
          'sms' => $config->sms ? true : false,
          'whatsapp_api_is_enable' => $config->whatsapp_api_is_enable ? true : false,
          'whatsapp_api' => $config->whatsapp_api ? true : false,
          'whatsapp_redirection_is_enable' => $config->whatsapp_redirection_is_enable ? true : false,
          'whatsapp_redirection' => $config->whatsapp_redirection ? true : false,
          'all_btn' => $config->all_btn
        ];
      }

      return $pages;
    }

  public static function getPageNames()
  {
    return [
      'Pre_Quote',
      'Quote',
      'Premium_Breakup',
      'Compare',
      'Proposal',
      'Proposal_Payment',
      'Payment_Success',
      'Payment_Failure',
      'Breakin_Success',
    ];
  }

}