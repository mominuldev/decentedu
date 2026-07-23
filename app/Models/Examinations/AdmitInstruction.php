<?php

namespace App\Models\Examinations;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdmitInstruction extends Model
{
    use BelongsToBranch, HasFactory;

    protected $fillable = ['instruction1', 'instruction2', 'instruction3', 'instruction4'];
}
