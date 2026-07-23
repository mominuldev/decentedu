<?php

namespace App\Models\Examinations;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Students\Enrollment;
use App\Models\Students\Student;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Not Auditable per-row: marks are entered/reprocessed in bulk (doc 08 K5 risk — a class×subject
// grid can be hundreds of rows per save), so row-level audit rows would swamp the log. MarksController
// and ResultController write one summary AuditLog entry per batch action instead.
class Mark extends Model
{
    use BelongsToBranch, HasFactory;

    protected $fillable = [
        'student_id', 'enrollment_id', 'mark_config_id', 'exam_id', 'obtained', 'is_absent', 'marked_by',
    ];

    protected function casts(): array
    {
        return ['is_absent' => 'boolean', 'obtained' => 'decimal:2'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function markConfig(): BelongsTo
    {
        return $this->belongsTo(MarkConfig::class);
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }
}
