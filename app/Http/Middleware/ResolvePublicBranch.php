<?php

namespace App\Http\Middleware;

use App\Support\BranchContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active branch for the public (rendered-site) API from configuration
 * rather than an authenticated session. The public marketing site has anonymous
 * visitors, so there is no user/session to derive a branch from; instead one branch
 * is pinned via config('cms.public_branch_id'). This publishes it to BranchContext so
 * the BelongsToBranch global scope isolates all downstream reads to that branch.
 *
 * Read-only: it deliberately does NOT set the spatie permissions team id (no auth here).
 */
class ResolvePublicBranch
{
    public function handle(Request $request, Closure $next): Response
    {
        $branchId = config('cms.public_branch_id');

        abort_if($branchId === null || $branchId === '', 503, 'The public site is not configured.');

        app(BranchContext::class)->set((int) $branchId);

        return $next($request);
    }
}
