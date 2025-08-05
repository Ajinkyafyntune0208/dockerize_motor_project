### Motor 2.0 Backend API

#### Kafta package
```bash
composer require mateusjunges/laravel-kafka
```

#### Razor Pay Proxy Changes
Open vendor/razorpay/razorpay/src/Request.php file and add below lines after $options array declaration.
```php
$proxy = config('constants.http_proxy');
if(!empty($proxy))
{
    $options['proxy'] = $proxy ;
}
unset($proxy);
```

#### CURL Error 35 the openssl changes are required if you are usign PHP 8.0 or less
Error : cURL error 35: OpenSSL/3.0.13: error:0A000152:SSL routines::unsafe legacy renegotiation disabled (see https://curl.haxx.se/libcurl/c/libcurl-errors.html)

OpenSsl Changes required :

```bash
##---
## Custom Configuration by Fyntune - starts
##

[openssl_init]
providers = provider_sect
ssl_conf = ssl_sect

[ssl_sect]
system_default = system_default_sect

[system_default_sect]
#MinProtocol = TLSv1.2
CipherString = DEFAULT:@SECLEVEL=2
Options = UnsafeLegacyServerConnect
Options = UnsafeLegacyRenegotiation
##
## Custom Configuration by Fyntune - ends
##---
```

#### Motor Api Token Generation

```bash
curl --location 'http://127.0.0.1:8000/api/tokenGeneration' \
--header 'Content-Type: application/json' \
--header 'Authorization: Basic base64encode(email:password)' \
--data '{
    "api_endpoint" : "http://127.0.0.1:8000/api/proposalReports"
}'
```
#### sbi pdf download steps

step_1 => make stage proposal drafted
step_2 => manually doo ckyc / ovd (first card) only , then hit the below curl (just replace the traceId)

curl : curl --location '--backend Api'/api/sbi-document-upload' \
--header 'Content-Type: application/json' \
--data '{
    "enquiry_id": ""
}'
$step_3 => check ckycWrapperLogs whether we get ckyc message as "file uploaded successfully on both the side" , if yes then rehit PDF.
