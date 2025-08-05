<?php
if (!function_exists('replaceSubdomains')) {
function replaceSubdomains($urls)
{
    $result = [];
    foreach ($urls as $url)
    {
        // Parse the URL to extract components
        $parsedUrl = parse_url($url);
        if (isset($parsedUrl['host']))
        {
            $host = $parsedUrl['host'];
            $scheme = isset($parsedUrl['scheme']) ? $parsedUrl['scheme'] : 'https';
            // Split the host into parts
            $domainParts = explode('.', $host);
            // Check if the domain has a subdomain (more than 2 parts)
            if (count($domainParts) > 2)
            {
                // Replace subdomain with '*'
                $mainDomain = '*.' . implode('.', array_slice($domainParts, -2));

                $result[] = "$scheme://".implode('.', array_slice($domainParts, -2));

                $result = array_unique($result);
            }
            else
            {
                // No subdomain, keep the domain as is
                $mainDomain = $host;
            }
            // Construct the URL with the wildcard domain
            //$formattedUrl = $scheme . '://' . $mainDomain;
            $formattedUrl = $mainDomain;
            // Avoid duplicates
            if (!in_array($formattedUrl, $result))
            {
                $result[] = $formattedUrl;
            }
        }
    }
    return $result;
}
}

$cors = [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['cv/payment-confirm/*', 'car/payment-confirm/*', 'bike/payment-confirm/*', 'api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];

if(isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] != '127.0.0.1' && !in_array( env('APP_ENV'), [ 'local' ] ) )
{
    $cors['allowed_origins'] =  replaceSubdomains([env('APP_URL'),env('APP_FRONTEND_URL')]);
}

$cors['allowed_origins'][] = 'http://localhost:*';

// $allHeaders = getallheaders();
// if( is_array($allHeaders) && isset($allHeaders['Exclude-Cors']) && $allHeaders['Exclude-Cors'] == "EfEUJNck#9eM" )
// {
//     $cors['allowed_origins'] = ['*']; 
// }
// if (env('REMOVE_ALLOW_ORIGIN_STAR')) {
//     $cors['allowed_origins'] = [env('APP_FRONTEND_URL')];
// }
return $cors;