<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommunicationConfiguration;
use Illuminate\Http\Request;

class CommunicationConfigurationController extends Controller
{

    public function index()
    {
      
      $pageNames = ['Pre Quote', 'Quote', 'Premium Breakup', 'Compare', 'Proposal', STAGE_NAMES['PAYMENT_SUCCESS'], STAGE_NAMES['PAYMENT_FAILED'], 'Breakin Success'];

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
        ];
      }

      return view('communicationconfiguration.index', compact('pages'));
    }

    public function store(Request $request)
    {

      $pageNames = ['Pre_Quote', 'Quote', 'Premium_Breakup', 'Compare', 'Proposal', 'Payment_Success', 'Payment_Failure', 'Breakin_Success'];

      $pagesAdditionalAddons = CommunicationConfiguration::distinct('page_name')->pluck('page_name')->toArray();

      $pageNames = array_unique(array_merge($pageNames, $pagesAdditionalAddons)); 

      $dataProcessed = false; // Flag to keep track
      foreach ($pageNames as $pageName) {

        $formattedPageName = str_replace('_', ' ', $pageName);

        $email = $request->has("{$pageName}_email");
        $sms = $request->has("{$pageName}_sms");
        $whatsappApi = $request->has("{$pageName}_whatsapp_api");
        $whatsappRedirection = $request->has("{$pageName}_whatsapp_redirection");

        $data = [
          'page_name' => str_replace('_', ' ', $pageName),
          'slug' => strtolower(str_replace(' ', '_', $pageName)),
          'email' => $email,
          'sms' => $sms,
          'whatsapp_api' => $whatsappApi,
          'whatsapp_redirection' => $whatsappRedirection,
        ];

        if ($email || $sms || $whatsappApi || $whatsappRedirection) {
          $dataProcessed = true;
          $existingConfig = CommunicationConfiguration::where('page_name', $formattedPageName)->first();
          
          if ($existingConfig) {

            $existingConfig->update($data);
          } else {

            CommunicationConfiguration::create($data);
          }
        }
      }

      if ($dataProcessed) {
        return redirect()->route('admin.common-configuration.index')->with('success', 'Configuration saved successfully.');
      } else {
        return redirect()->route('admin.common-configuration.index')->with('error', 'No valid data to save.');
      }
    }


    public function getBrokerThemeData()
    {
      $pageNames = [
        'Pre Quote',
        'Quote',
        'Premium Breakup',
        'Compare',
        'Proposal',
        STAGE_NAMES['PAYMENT_SUCCESS'],
        STAGE_NAMES['PAYMENT_FAILED'],
        'Breakin Success'
      ];

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
        ];
      }

      return $pages;
    }
}