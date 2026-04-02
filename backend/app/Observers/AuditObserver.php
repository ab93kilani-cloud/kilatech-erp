<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditObserver
{
    private function logAction(Model $model, string $action)
    {
        // Don't log actions if there's no authenticated user for now
        // (can be adjusted later if system actions need to be logged)
        $userId = Auth::id();

        // Prevent logging the AuditLog itself to avoid infinite loops
        if ($model instanceof AuditLog) {
            return;
        }

        AuditLog::create([
            'user_id' => $userId,
            'action' => $action,
            'table_name' => $model->getTable(),
            'record_id' => $model->getKey(),
            'old_value' => $action === 'updated' || $action === 'deleted' ? $model->getOriginal() : null,
            'new_value' => $action === 'created' || $action === 'updated' ? $model->getAttributes() : null,
            'ip_address' => request()->ip(),
        ]);
    }

    public function created(Model $model): void
    {
        $this->logAction($model, 'created');
    }

    public function updated(Model $model): void
    {
        $this->logAction($model, 'updated');
    }

    public function deleted(Model $model): void
    {
        $this->logAction($model, 'deleted');
    }

    // Capture forceDelete as well
    public function forceDeleted(Model $model): void
    {
        $this->logAction($model, 'force_deleted');
    }
}
