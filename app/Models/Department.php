<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasFactory;

    protected $table = 'departments';

    protected $fillable = [
        'division_id',
        'name',
        'code',
        'cc_emails',
    ];

    /**
     * Get the division that owns the department.
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    /**
     * Get the contracts for this department.
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'department_id');
    }
}
