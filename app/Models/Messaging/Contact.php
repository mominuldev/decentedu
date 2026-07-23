<?php

namespace App\Models\Messaging;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Hr\Employee;
use App\Models\Students\Student;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    use BelongsToBranch, HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'phone', 'type', 'student_id', 'employee_id', 'status', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['status' => 'boolean'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
