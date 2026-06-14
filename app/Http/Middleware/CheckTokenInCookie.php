<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class CheckTokenInCookie
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->cookie("token")) {
            //Log::info("cookie found in test: " . $request->cookie("token"));
            $request->headers->set(
                "Authorization",
                "Bearer " . $request->cookie("token"),
            );
        } 
        elseif ($request->header("Authorization")) {
        } 
        else {
            Log::info("no cookie found from test");
            // return response()->json(['status'=>false,'msg'=>'not an authenticated user!']);
        }
        return $next($request);
    }
}
