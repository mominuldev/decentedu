<?php

namespace App\Models\Students;

use App\Models\Concerns\BelongsToBranch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Guardian extends Model
{
    use BelongsToBranch, HasFactory, SoftDeletes;

    protected $fillable = [
        'branch_id',
        'student_id',
        'relationship',
        'name',
        'mobile',
        'email',
        'address',
        'photo_path',
        'occupation',
        'nid',
        'is_emergency_contact',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_emergency_contact' => 'boolean',
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

    // Scope for emergency contacts
    public function scopeEmergencyContact($query)
    {
        return $query->where('is_emergency_contact', true);
    }

    // Scope for specific relationship type
    public function scopeRelationship($query, $relationship)
    {
        return $query->where('relationship', $relationship);
    }
}