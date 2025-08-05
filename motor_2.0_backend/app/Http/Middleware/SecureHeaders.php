<?php
namespace App\Http\Middleware;
use Illuminate\Http\Request;
use Closure;

class SecureHeaders
{
    // Enumerate headers which you do not want in your application's responses.
    // Great starting point would be to go check out @Scott_Helme's:
    // https://securityheaders.com/
    private $unwantedHeaderList = [
        'X-Powered-By',
        'Server',
        'x-ratelimit-limit',
        'x-ratelimit-remaining'
    ];
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        if ( config('security.headers.enabled') == "Y")
        {
            $domainToAppend = [];
            if( !empty( env( 'APP_URL' ) ) )
            {
                $domainToAppend[] = env( 'APP_URL' );
            }

            if( !empty( env( 'APP_FRONTEND_URL' ) ) )
            {
                $domainToAppend[] = env( 'APP_FRONTEND_URL' );
            }
            $domainToAppend = implode( " ", $domainToAppend );
            $response->headers->set('Cache-Control', "no-cache, private always;" );
            $response->headers->set('Referrer-Policy', "strict-origin-when-cross-origin always;" );
            $response->headers->set('X-Content-Type-Options', 'nosniff');
            $response->headers->set('X-XSS-Protection', '1; mode=block');
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN always;');
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload always');
            # $response->headers->set('Content-Security-Policy', "default-src 'self'; frame-src 'self'; script-src 'self' cdn.example.com https://ajax.googleapis.com https://*.s3.ap-southeast-1.amazonaws.com https://unpkg.com https://stackpath.bootstrapcdn.com https://cdnjs.cloudflare.com https://www.gstatic.com https://kwikid.s3.ap-south-1.amazonaws.com https://*.s3-ap-southeast-1.amazonaws.com ".$domainToAppend."; font-src 'self' cdn.example.com https://fonts.googleapis.com https://fonts.gstatic.com https://www.gstatic.com https://cdnjs.cloudflare.com https://stackpath.bootstrapcdn.com; style-src 'self' https://fonts.googleapis.com https://stackpath.bootstrapcdn.com https://cdnjs.cloudflare.com; img-src 'self' data: https://*.s3.ap-southeast-1.amazonaws.com https://kwikid.s3.ap-south-1.amazonaws.com https://www.gstatic.com https://*.s3.ap-south-1.amazonaws.com; connect-src 'self' ".$domainToAppend.";" );
            unset( $domainToAppend );
        }
        $this->removeUnwantedHeaders($this->unwantedHeaderList);
        foreach ( $this->unwantedHeaderList as $header)
        {
            $response->headers->remove( $header );
        }
        return $response;
    }
  
    private function removeUnwantedHeaders($headerList)
    {
        foreach ($headerList as $header)
            header_remove($header);
    }
}
?>