<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCanManageTeacherAssignments
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->canManageTeacherAssignments()) {
            abort(403, 'Only System Admin or Head of School may manage teacher assignments.');
        }

        return $next($request);
    }
}
