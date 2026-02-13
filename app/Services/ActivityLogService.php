<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ActivityLogService
{
    /**
     * Log a create action for the given model.
     */
    public function logCreated(Model $model, ?int $userId = null): ActivityLog
    {
        return ActivityLog::create([
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
     * Log an update action for the given model.
     */
    public function logUpdated(Model $model, array $oldValues, ?int $userId = null): ActivityLog
    {
        return ActivityLog::create([
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
     * Log a delete action for the given model.
     */
    public function logDeleted(Model $model, ?int $userId = null): ActivityLog
    {
        return ActivityLog::create([
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
     * Log a custom activity for any model.
     */
    public function logCustom(Model $model, string $event, string $description, ?User $user = null): ActivityLog
    {
        return $model->activityLogs()->create([
            'LOG_CAUSER_ID' => $user?->LGL_ROW_ID ?? Auth::id(),
            'LOG_CAUSER_TYPE' => User::class,
            'LOG_EVENT' => $event,
            'LOG_DESC' => $description,
            'LOG_PROPERTIES' => [
                'model_type' => get_class($model),
                'model_id' => $model->getKey(),
            ],
            'LOG_NAME' => 'custom_activity',
        ]);
    }
}
