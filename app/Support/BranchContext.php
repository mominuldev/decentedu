<?php

namespace App\Support;

/**
 * Holds the active branch for the current request. Set once by the
 * EnsureBranchContext middleware and read by the BelongsToBranch global scope,
 * so tenant data is automatically isolated to one branch.
 */
class BranchContext
{
    private ?int $branchId = null;

    public function set(?int $branchId): void
    {
        $this->branchId = $branchId;
    }

    public function id(): ?int
    {
        return $this->branchId;
    }

    public function has(): bool
    {
        return $this->branchId !== null;
    }

    /** Assert a branch is active or fail loudly (guards against unscoped writes). */
    public function idOrFail(): int
    {
        if ($this->branchId === null) {
            abort(409, 'No active branch selected.');
        }

        return $this->branchId;
    }
}
