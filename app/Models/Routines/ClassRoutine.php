<?php

namespace App\Models\Routines;

use App\Models\Academic\ClassConfig;
use App\Models\Academic\Subject;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Hr\Employee;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClassRoutine extends Model
{
    use BelongsToBranch, HasFactory, SoftDeletes;

    protected $fillable = [
        'class_config_id', 'period_id', 'day_of_week', 'subject_id', 'employee_id',
        'room', 'status', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['status' => 'boolean', 'day_of_week' => 'integer'];
    }

    public function classConfig(): BelongsTo
    {
        return $this->belongsTo(ClassConfig::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
