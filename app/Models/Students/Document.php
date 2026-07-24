<?php

namespace App\Models\Students;

use App\Models\Concerns\BelongsToBranch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use BelongsToBranch, HasFactory, SoftDeletes;

    protected $table = 'student_documents';

    protected $fillable = [
        'branch_id',
        'student_id',
        'document_type',
        'document_number',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
        'description',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scope for specific document type
    public function scopeType($query, $type)
    {
        return $query->where('document_type', $type);
    }
}
