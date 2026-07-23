<?php

namespace App\Models\Examinations;

use App\Models\Academic\SchoolClass;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ExamConfig extends Model
{
    use BelongsToBranch, HasFactory;

    protected $fillable = ['class_id', 'merit_basis', 'merit_sequential', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['merit_sequential' => 'boolean'];
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /** The exams that count toward this class's result (e.g. the two exams a Grand Final combines). */
    public function exams(): BelongsToMany
    {
        return $this->belongsToMany(Exam::class, 'exam_config_exam');
    }
}
