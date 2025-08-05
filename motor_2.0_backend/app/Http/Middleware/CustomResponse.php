<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CustomResponse
{

    protected $except = [
        'api/aceCrmLeadId',
        'api/renewbuy/motor/kafkaMessages'
    ];

    protected $pattern = [
        'admin/log-viewer/*',
    ];
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        pushTraceIdInLogs();
        $response = $next($request);

        // If the path matches the $pattern array then don't do the custom response
        if (config('app.debug') === true) {
            foreach ($this->pattern as $v) {
                if (fnmatch($v, $request->path())) {
                    return $response;
                }
            }
        }
        // If the path is in exclude array then don't do the custom response
        if (config('app.debug') === true && !in_array($request->path(), $this->except)) {
            if ($response instanceof BinaryFileResponse) {
                return $response;
            }
            $response = $response->header(
                'X-FRAME-OPTIONS',
                'SAMEORIGIN'
            )->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
            if ($response instanceof \Illuminate\Http\JsonResponse) {
                // $sql_data = [];
                // $total_query_time = 0;
                // foreach(\Illuminate\Support\Facades\DB::getQueryLog() as $key => $sql){
                //     $total_query_time = $total_query_time + $sql['time'];
                //     // $addSlashes = str_replace('?', "'?'", $sql['query']);
                //     // $sql_data[] = [
                //     //     'query' => vsprintf(str_replace('?', '%s', $addSlashes), $sql['bindings'] ?? []),
                //     //     'time' => $sql['time'] . ' ms'
                //     // ];
                // }

                // $log = ['queries' => \Illuminate\Support\Facades\DB::getQueryLog(), 'total_query_time' => $total_query_time . ' ms', 'memory' => round(memory_get_usage() / 1048576, 2) . ' MB'];
                $log = [];
                $response_data = array_merge(json_decode($response->content(), true), $log);
                try {
                    $response->setData($response_data);
                } catch (\Exception $e) {
                    return $response;
                }
                return $response;
            }
        }
        return $response;
    }
}
