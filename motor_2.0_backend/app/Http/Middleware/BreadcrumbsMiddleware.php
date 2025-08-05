<?php
namespace App\Http\Middleware;

use Closure;
use App\Models\Menu;

class BreadcrumbsMiddleware
{
    public function handle($request, Closure $next)
    {
        $currentUrl = '/' . $request->path(); // Get the current URL path and ensure it starts with a slash
        $breadcrumbs = Menu::getBreadcrumbsByUrl($currentUrl);

        view()->share('breadcrumbs', $breadcrumbs);

        return $next($request);
    }
}
?>