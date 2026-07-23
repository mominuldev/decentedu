<?php

namespace App\Http\Middleware;

use App\Support\BranchContext;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active branch for an authenticated request (from the session,
 * falling back to the user's default), verifies membership, and publishes it to
 * BranchContext + the spatie team id so all downstream queries and permission
 * checks are scoped to that branch.
 */
class EnsureBranchContext
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $user = $request->user();

            if ($user) {
                $branchId = null;

                try {
                    $session = $request->getSession();
                } catch (\Exception $e) {
                    $session = null;
                }

                // Only try to access session if it's available and started
                if ($session && $session->isStarted()) {
                    try {
                        $branchId = $session->get('active_branch_id');

                        // Drop a stale/foreign branch id.
                        if ($branchId && ! $user->branches()->where('branches.id', $branchId)->where('branches.status', true)->exists()) {
                            $branchId = null;
                            $session->forget('active_branch_id');
                        }

                        // Fall back to the pinned/first branch.
                        if (! $branchId) {
                            $default = $user->defaultBranch();
                            if ($default) {
                                $branchId = $default->id;
                                $session->put('active_branch_id', $branchId);
                            }
                        }
                    } catch (\Exception $e) {
                        // Fall through to use default branch
                    }
                }

                // In testing/CLI without session or on errors, use default branch
                if (! $branchId) {
                    $default = $user->defaultBranch();
                    if ($default) {
                        $branchId = $default->id;
                    }
                }

                app(BranchContext::class)->set($branchId);
                app(PermissionRegistrar::class)->setPermissionsTeamId($branchId);
            }
        } catch (\Exception $e) {
            // Log but don't block the request
            if (config('app.debug')) {
                logger()->error('EnsureBranchContext error: ' . $e->getMessage());
            }
        }

        return $next($request);
    }
}
