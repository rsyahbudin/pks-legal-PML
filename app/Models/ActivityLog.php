<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;

class ActivityLog extends Model
{
    use HasFactory;

    protected $table = 'LGL_USER_ADTRL_LOG';

    protected $primaryKey = 'LGL_ROW_ID';

    const CREATED_AT = 'REF_CONTR_CREATED_DT'; // Migration uses this (likely typo but must match)

    const UPDATED_AT = 'REF_CONTR_UPDATED_DT';

    protected $fillable = [
        'LOG_NAME',
        'LOG_DESC',
        'LOG_SUBJECT_TYPE',
        'LOG_SUBJECT_ID',
        'LOG_EVENT',
        'LOG_CAUSER_TYPE',
        'LOG_CAUSER_ID',
        'LOG_PROPERTIES',
        'LOG_BATCH_UUID',
        'LOG_OLD_VALUES',
        'LOG_NEW_VALUES',
        'REF_CONTR_CREATED_BY',
        'REF_CONTR_UPDATED_BY',
    ];

    protected function casts(): array
    {
        return [
            'LOG_PROPERTIES' => 'array',
            'LOG_OLD_VALUES' => 'array',
            'LOG_NEW_VALUES' => 'array',
        ];
    }

    /**
     * Get the user who performed the action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'LOG_CAUSER_ID');
    }

    /**
     * Get the created_at attribute.
     */
    public function getCreatedAtAttribute()
    {
        return $this->REF_CONTR_CREATED_DT;
    }

    /**
     * Get the updated_at attribute.
     */
    public function getUpdatedAtAttribute()
    {
        return $this->REF_CONTR_UPDATED_DT;
    }

    /**
     * Get the loggable entity.
     */
    public function loggable(): MorphTo
    {
        return $this->morphTo(null, 'LOG_SUBJECT_TYPE', 'LOG_SUBJECT_ID');
    }

    /**
     * Log a create action.
     */
    public static function logCreated(Model $model, ?int $userId = null): self
    {
        return static::create([
            'LOG_CAUSER_ID' => $userId ?? Auth::id(),
            'LOG_CAUSER_TYPE' => User::class,
            'LOG_EVENT' => 'created',
            'LOG_SUBJECT_TYPE' => get_class($model),
            'LOG_SUBJECT_ID' => $model->getKey(),
            'LOG_NEW_VALUES' => $model->toArray(),
            'LOG_NAME' => 'default',
        ]);
    }

    /**
     * Log an update action.
     */
    public static function logUpdated(Model $model, array $oldValues, ?int $userId = null): self
    {
        return static::create([
            'LOG_CAUSER_ID' => $userId ?? Auth::id(),
            'LOG_CAUSER_TYPE' => User::class,
            'LOG_EVENT' => 'updated',
            'LOG_SUBJECT_TYPE' => get_class($model),
            'LOG_SUBJECT_ID' => $model->getKey(),
            'LOG_OLD_VALUES' => $oldValues,
            'LOG_NEW_VALUES' => $model->toArray(),
            'LOG_NAME' => 'default',
        ]);
    }

    /**
     * Log a delete action.
     */
    public static function logDeleted(Model $model, ?int $userId = null): self
    {
        return static::create([
            'LOG_CAUSER_ID' => $userId ?? Auth::id(),
            'LOG_CAUSER_TYPE' => User::class,
            'LOG_EVENT' => 'deleted',
            'LOG_SUBJECT_TYPE' => get_class($model),
            'LOG_SUBJECT_ID' => $model->getKey(),
            'LOG_OLD_VALUES' => $model->toArray(),
            'LOG_NAME' => 'default',
        ]);
    }

    /**
     * Get action label in English.
     */
    public function getActionLabelAttribute(): string
    {
        return match ($this->LOG_EVENT) {
            'created' => 'Created',
            'updated' => 'Updated',
            'deleted' => 'Deleted',
            default => $this->LOG_EVENT,
        };
    }

    /**
     * Get the action description.
     */
    public function getActionAttribute(): string
    {
        return $this->LOG_DESC ?? $this->action_label;
    }

    /**
     * Get detailed changes description.
     */
    public function getChangesDescriptionAttribute(): ?string
    {
        if ($this->LOG_EVENT !== 'updated' || ! $this->LOG_OLD_VALUES || ! $this->LOG_NEW_VALUES) {
            return null;
        }

        $changes = [];
        $fieldLabels = [
            'contract_number' => 'Contract Number',
            'CONTR_NO' => 'Contract Number',
            'division_id' => 'Division',
            'DIV_ID' => 'Division',
            'department_id' => 'Department',
            'DEPT_ID' => 'Department',
            'pic_id' => 'PIC',
            'CONTR_PIC' => 'PIC',
            'pic_name' => 'PIC Name', // pic_name removed?
            'pic_email' => 'PIC Email', // pic_email removed?
            'start_date' => 'Start Date',
            'CONTR_START_DT' => 'Start Date',
            'end_date' => 'End Date',
            'CONTR_END_DT' => 'End Date',
            'is_auto_renewal' => 'Auto Renewal',
            'CONTR_IS_AUTO_RENEW' => 'Auto Renewal',
            'description' => 'Description',
            'CONTR_DESC' => 'Description',
            'REF_DIV_DESC' => 'Description',
            'REF_DIV_NAME' => 'Division Name',
            'IS_ACTIVE' => 'Active Status',
            'status' => 'Status',
            'CONTR_STS_ID' => 'Status', // Value might be ID. Description logic handles 'status' special case?
            'document_path' => 'Document',
            'CONTR_DOC_PATH' => 'Document',
        ];

        $statusLabels = [
            'draft' => 'Draft',
            'active' => 'Active',
            'expired' => 'Expired',
            'terminated' => 'Terminated',
        ];

        foreach ($fieldLabels as $field => $label) {
            $oldValue = $this->LOG_OLD_VALUES[$field] ?? null;
            $newValue = $this->LOG_NEW_VALUES[$field] ?? null;

            if ($oldValue != $newValue) {
                // Format values based on field type
                if (in_array($field, ['status', 'CONTR_STS_ID'])) {
                    // If ID, we might need to lookup. But old logic used string code?
                    // Migration: contract_statuses id used.
                    // If values are IDs, this map logic 'draft'/'active' might not work unless values are strings.
                    // Assuming they are strings or IDs mixed.
                    $oldValue = $statusLabels[$oldValue] ?? $oldValue;
                    $newValue = $statusLabels[$newValue] ?? $newValue;
                } elseif (in_array($field, ['is_auto_renewal', 'CONTR_IS_AUTO_RENEW'])) {
                    $oldValue = $oldValue ? 'Yes' : 'No';
                    $newValue = $newValue ? 'Yes' : 'No';
                } elseif (in_array($field, ['start_date', 'end_date', 'CONTR_START_DT', 'CONTR_END_DT'])) {
                    if ($oldValue) {
                        try {
                            $oldValue = \Carbon\Carbon::parse($oldValue)->format('d F Y');
                        } catch (\Exception $e) {
                        }
                    }
                    if ($newValue) {
                        try {
                            $newValue = \Carbon\Carbon::parse($newValue)->format('d F Y');
                        } catch (\Exception $e) {
                        }
                    }
                } elseif (in_array($field, ['document_path', 'CONTR_DOC_PATH'])) {
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
