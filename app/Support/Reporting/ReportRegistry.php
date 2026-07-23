<?php

namespace App\Support\Reporting;

use App\Support\Reporting\Definitions\AdmitCardReport;
use App\Support\Reporting\Definitions\AttendanceSheetReport;
use App\Support\Reporting\Definitions\FailListReport;
use App\Support\Reporting\Definitions\FeeDailyCollectionReport;
use App\Support\Reporting\Definitions\FeeDuesSummaryReport;
use App\Support\Reporting\Definitions\IncomeStatementReport;
use App\Support\Reporting\Definitions\MarksheetReport;
use App\Support\Reporting\Definitions\MeritListReport;
use App\Support\Reporting\Definitions\SeatPlanReport;
use App\Support\Reporting\Definitions\TabulationSheetReport;
use App\Support\Reporting\Definitions\TrialBalanceReport;

/**
 * Slug whitelist for the generic ReportController, same pattern as the module
 * SetupControllers (routes/api.php) — add new reports here, not a new controller.
 */
class ReportRegistry
{
    /** @var array<string, class-string<ReportDefinition>> */
    private const DEFINITIONS = [
        'marksheet' => MarksheetReport::class,
        'tabulation-sheet' => TabulationSheetReport::class,
        'merit-list' => MeritListReport::class,
        'fail-list' => FailListReport::class,
        'admit-card' => AdmitCardReport::class,
        'seat-plan' => SeatPlanReport::class,
        'attendance-sheet' => AttendanceSheetReport::class,
        'fee-daily-collection' => FeeDailyCollectionReport::class,
        'fee-dues-summary' => FeeDuesSummaryReport::class,
        'trial-balance' => TrialBalanceReport::class,
        'income-statement' => IncomeStatementReport::class,
    ];

    /** Pipe-delimited slugs for a route ->where() constraint. */
    public static function keys(): string
    {
        return implode('|', array_keys(self::DEFINITIONS));
    }

    public static function resolve(string $key): ReportDefinition
    {
        abort_unless(isset(self::DEFINITIONS[$key]), 404, 'Unknown report.');

        return app(self::DEFINITIONS[$key]);
    }
}
