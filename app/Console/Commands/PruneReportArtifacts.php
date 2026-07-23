<?php

namespace App\Console\Commands;

use App\Models\Reporting\ReportArtifact;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/** Retention for generated report files (doc 08: "artifact cached by params hash; retention policy"). */
class PruneReportArtifacts extends Command
{
    protected $signature = 'reports:prune {--days=30 : Delete artifacts older than this many days}';

    protected $description = 'Delete report artifact files and rows past the retention window';

    public function handle(): int
    {
        $cutoff = now()->subDays((int) $this->option('days'));

        $artifacts = ReportArtifact::withoutBranchScope()->where('created_at', '<', $cutoff)->get();

        foreach ($artifacts as $artifact) {
            if ($artifact->file_path) {
                Storage::delete($artifact->file_path);
            }
            $artifact->delete();
        }

        $this->info("Pruned {$artifacts->count()} report artifact(s) older than {$cutoff->toDateString()}.");

        return self::SUCCESS;
    }
}
