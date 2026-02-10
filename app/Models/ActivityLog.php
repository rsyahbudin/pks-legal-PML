<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'action',
        'loggable_type',
        'loggable_id',
        'old_values',
        'new_values',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    /**
     * Get the user who performed the action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the loggable entity.
     */
    public function loggable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Log a create action.
     */
    public static function logCreated(Model $model, ?int $userId = null): self
    {
        return static::create([
            'user_id' => $userId ?? auth()->id(),
            'action' => 'created',
            'loggable_type' => get_class($model),
            'loggable_id' => $model->id,
            'new_values' => $model->toArray(),
        ]);
    }

    /**
     * Log an update action.
     */
    public static function logUpdated(Model $model, array $oldValues, ?int $userId = null): self
    {
        return static::create([
            'user_id' => $userId ?? auth()->id(),
            'action' => 'updated',
            'loggable_type' => get_class($model),
            'loggable_id' => $model->id,
            'old_values' => $oldValues,
            'new_values' => $model->toArray(),
        ]);
    }

    /**
     * Log a delete action.
     */
    public static function logDeleted(Model $model, ?int $userId = null): self
    {
        return static::create([
            'user_id' => $userId ?? auth()->id(),
            'action' => 'deleted',
            'loggable_type' => get_class($model),
            'loggable_id' => $model->id,
            'old_values' => $model->toArray(),
        ]);
    }

    /**
     * Get action label in English.
     */
    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            'created' => 'Created',
            'updated' => 'Updated',
            'deleted' => 'Deleted',
            default => $this->action,
        };
    }

    /**
     * Get detailed changes description.
     */
    public function getChangesDescriptionAttribute(): ?string
    {
        if ($this->action !== 'updated' || ! $this->old_values || ! $this->new_values) {
            return null;
        }

        $changes = [];
        $fieldLabels = [
            'contract_number' => 'Contract Number',
            'division_id' => 'Division',
            'department_id' => 'Department',
            'pic_id' => 'PIC',
            'pic_name' => 'PIC Name',
            'pic_email' => 'PIC Email',
            'start_date' => 'Start Date',
            'end_date' => 'End Date',
            'is_auto_renewal' => 'Auto Renewal',
            'description' => 'Description',
            'status' => 'Status',
            'document_path' => 'Document',
        ];

        $statusLabels = [
            'draft' => 'Draft',
            'active' => 'Active',
            'expired' => 'Expired',
            'terminated' => 'Terminated',
        ];

        foreach ($fieldLabels as $field => $label) {
            $oldValue = $this->old_values[$field] ?? null;
            $newValue = $this->new_values[$field] ?? null;

            if ($oldValue != $newValue) {
                // Format values based on field type
                if ($field === 'status') {
                    $oldValue = $statusLabels[$oldValue] ?? $oldValue;
                    $newValue = $statusLabels[$newValue] ?? $newValue;
                } elseif ($field === 'is_auto_renewal') {
                    $oldValue = $oldValue ? 'Yes' : 'No';
                    $newValue = $newValue ? 'Yes' : 'No';
                } elseif (in_array($field, ['start_date', 'end_date'])) {
                    if ($oldValue) {
                        $oldValue = \Carbon\Carbon::parse($oldValue)->format('d F Y');
                    }
                    if ($newValue) {
                        $newValue = \Carbon\Carbon::parse($newValue)->format('d F Y');
                    }
                } elseif ($field === 'document_path') {
                    $oldValue = $oldValue ? 'Exists' : 'None';
                    $newValue = $newValue ? 'Exists' : 'None';
                }

                if ($oldValue || $newValue) {
                    $changes[] = sprintf(
                        '%s changed from "%s" to "%s"',
                        $label,
                        $oldValue ?: '-',
                        $newValue ?: '-'
                    );
                }
            }
        }

        return ! empty($changes) ? implode(', ', $changes) : null;
    }
}
