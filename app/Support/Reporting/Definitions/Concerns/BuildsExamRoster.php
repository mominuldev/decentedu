<?php

namespace App\Support\Reporting\Definitions\Concerns;

use App\Models\Students\Enrollment;
use Illuminate\Support\Collection;

/** Shared roster query behind admit card, seat plan and attendance sheet. */
trait BuildsExamRoster
{
    protected function roster(int $classConfigId, ?int $groupId): Collection
    {
        return Enrollment::with('student')
            ->where('class_config_id', $classConfigId)
            ->when($groupId, fn ($q) => $q->where('group_id', $groupId))
            ->current()
            ->get()
            ->sortBy('roll')
            ->values();
    }
}
