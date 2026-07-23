<?php

namespace App\Models\Examinations;

use App\Models\Academic\ClassConfig;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Hr\Employee;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassTeacherConfig extends Model
{
    use BelongsToBranch, HasFactory;

    protected $fillable = ['class_config_id', 'employee_id'];

    public function classConfig(): BelongsTo
    {
        return $this->belongsTo(ClassConfig::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
