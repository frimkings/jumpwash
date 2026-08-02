<?php

namespace App\Http\Middleware;

use App\Support\BranchContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetBranchContext
{
    public function __construct(private readonly BranchContext $branchContext)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check() && ! session()->has('active_branch_id')) {
            $this->branchContext->syncFromUser(auth()->user());
        }

        return $next($request);
    }
}