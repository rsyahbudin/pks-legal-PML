<?php

namespace App\Models\Concerns\Ticket;

use Illuminate\Database\Eloquent\Builder;

trait HasScopes
{
    /**
     * Scope for open tickets.
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereHas('status', fn ($q) => $q->where('LOV_VALUE', 'open'));
    }

    /**
     * Scope for tickets that need review (open status).
     */
    public function scopeNeedReview(Builder $query): Builder
    {
        return $query->whereHas('status', fn ($q) => $q->where('LOV_VALUE', 'open'));
    }

    /**
     * Scope for on process tickets.
     */
    public function scopeOnProcess(Builder $query): Builder
    {
        return $query->whereHas('status', fn ($q) => $q->where('LOV_VALUE', 'on_process'));
    }

    /**
     * Scope for done tickets.
     */
    public function scopeDone(Builder $query): Builder
    {
        return $query->whereHas('status', fn ($q) => $q->where('LOV_VALUE', 'done'));
    }

    /**
     * Scope for rejected tickets.
     */
    public function scopeRejected(Builder $query): Builder
    {
        return $query->whereHas('status', fn ($q) => $q->where('LOV_VALUE', 'rejected'));
    }

    /**
     * Scope for closed tickets.
     */
    public function scopeClosed(Builder $query): Builder
    {
        return $query->whereHas('status', fn ($q) => $q->where('LOV_VALUE', 'closed'));
    }

    /**
     * Scope for tickets created by a specific user.
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('TCKT_CREATED_BY', $userId);
    }
}
