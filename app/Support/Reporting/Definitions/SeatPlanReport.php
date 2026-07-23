<?php

namespace App\Support\Reporting\Definitions;

use App\Support\BranchContext;
use App\Support\Reporting\Definitions\Concerns\BuildsExamRoster;
use App\Support\Reporting\ReportDefinition;
use Illuminate\Validation\Rule;

/** Sequential seat allocation by roll, filling one room to capacity before moving to the next. */
class SeatPlanReport extends ReportDefinition
{
    use BuildsExamRoster;

    public function key(): string
    {
        return 'seat-plan';
    }

    public function title(): string
    {
        return 'Seat Plan';
    }

    public function rules(): array
    {
        $branchId = app(BranchContext::class)->idOrFail();

        return [
            'class_config_id' => ['required', 'integer', Rule::exists('class_configs', 'id')->where('branch_id', $branchId)],
            'group_id' => ['nullable', 'integer'],
            'rooms' => ['required', 'array', 'min:1'],
            'rooms.*.name' => ['required', 'string'],
            'rooms.*.capacity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function data(array $params): array
    {
        $students = $this->roster($params['class_config_id'], $params['group_id'] ?? null);
        $rows = collect();
        $roomIndex = 0;
        $seatInRoom = 0;

        foreach ($students as $e) {
            while ($roomIndex < count($params['rooms']) && $seatInRoom >= $params['rooms'][$roomIndex]['capacity']) {
                $roomIndex++;
                $seatInRoom = 0;
            }
            if ($roomIndex >= count($params['rooms'])) {
                break; // out of capacity
            }
            $seatInRoom++;
            $rows->push([
                'student_id' => $e->student_id,
                'roll' => $e->roll,
                'name' => $e->student?->name,
                'room' => $params['rooms'][$roomIndex]['name'],
                'seat_no' => $seatInRoom,
            ]);
        }

        return ['rows' => $rows->values(), 'branch' => $this->branch()];
    }

    public function pdfView(): ?string
    {
        return 'reports.examinations.seat-plan';
    }

    public function excelHeadings(): ?array
    {
        return ['Roll', 'Name', 'Room', 'Seat No'];
    }

    public function excelRows(array $data): array
    {
        return $data['rows']->map(fn (array $r) => [$r['roll'], $r['name'], $r['room'], $r['seat_no']])->all();
    }
}
