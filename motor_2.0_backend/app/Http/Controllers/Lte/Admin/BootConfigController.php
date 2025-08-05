<?php

namespace App\Http\Controllers\Lte\Admin;

use Exception;
use App\Models\BootConfig;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class BootConfigController extends Controller
{
  /*---x----Boot configuration for Env file---x----*/
   protected $envFile      = 'ENV_MOTOR_AS_REQUIRED.txt';
   protected $excludedKeys = [
                                'APP_NAME','APP_ENV','APP_KEY', 'DB_PASSWORD','DB_DATABASE',
                                'DB_USERNAME','DB_PORT','DB_HOST',
                                'DB_CONNECTION', 'MAIL_MAILER', 'MAIL_HOST',
                                'MAIL_PORT','MAIL_USERNAME','MAIL_PASSWORD',
                                'MAIL_ENCRYPTION', 'MAIL_FROM_ADDRESS', 'MAIL_FROM_NAME'
                             ];

   public function show ()
   {
    $defaultEnv = $this->getEnvKeys();

    $envVars = [];
    foreach ($defaultEnv as $key => $defaultValue) 
    {
        $dbValue       = BootConfig::where('key', $key)->value('value');
        $envVars[$key] = $dbValue ?? $defaultValue; 
    }

    return view('admin_lte.boot-config.env-boot-config', compact('envVars'));
   }

   protected function getEnvKeys(): array
   {
       $path = base_path('.env');
       if (!file_exists($path)) return [];
       
       $lines   = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
       $envVars = [];
       
       foreach ($lines as $line) {
         if (Str::startsWith(trim($line), '#') || !Str::contains($line, '=')) continue;
         
         [$key, $value] = explode('=', $line, 2);
         
         if (!in_array(trim($key), $this->excludedKeys)) {
           $envVars[trim($key)] = trim($value);
          }
        }
       return $envVars;
   }

   public function update(Request $request)
   {
       $data = $request->except('_token');
       try
       {
            foreach ($data as $key => $value) 
            {
            BootConfig::updateOrCreate(
                ['key'    => $key], 
                ['value'  => $value]
                );
            }  
            $this->updateEnvFile($data);
            
            \Illuminate\Support\Facades\Artisan::call('optimize:clear');
            return redirect()->back()->with('success', 'Environment updated successfully.');
       }
       catch(Exception $e)
       {
           return redirect()->back()->with('error', 'Failed to update environment. Please try again later.');
       }

   }

   protected function updateEnvFile(array $data)
   {
       $path    = base_path('.env');
       $content = file_exists($path) ? file_get_contents($path) : '';

       foreach ($data as $key => $value) {
           $pattern = "/^$key=.*$/m";
           $replacement = "$key=$value";

           if (preg_match($pattern, $content)) {
               $content = preg_replace($pattern, $replacement, $content);
           } else {
               $content .= "\n$replacement";
           }
       }

       file_put_contents($path, $content);
   }

   
}
