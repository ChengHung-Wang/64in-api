<?php

namespace App\Http\Middleware;

use App\Models\Users;
use Closure;
use Illuminate\Http\Request;

class TokenCheck
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if(! $request->hasHeader("X-User-Token") || $request->header("X-User-Token") == "")
            return err("MSG_INVALID_TOKEN", 401);
        $token = $request->header("X-User-Token");
        $this_user = Users::where("token", $token)->first();
        if(empty($this_user))
            return err("MSG_INVALID_TOKEN", 401);
        $request->attributes->add([
            "this_user" => $this_user
        ]);
        return $next($request);
    }
}
