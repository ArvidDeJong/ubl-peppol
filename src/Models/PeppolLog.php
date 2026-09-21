<?php

namespace Darvis\UblPeppol\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class PeppolLog extends Model
{
    protected $fillable = [
        'invoice_id',
        'invoice_nr',
        'status',
        'http_status_code',
        'message',
        'error',
        'response',
        'sent_at',
    ];

    protected $casts = [
        'response' => 'array',
        'sent_at' => 'datetime',
    ];

    /**
     * Whether the host app published and ran the migration. The table is opt-in, so everything
     * that writes a log asks this first instead of assuming it is there.
     */
    public static function tableExists(): bool
    {
        $model = static::query()->getModel();

        return Schema::connection($model->getConnectionName())->hasTable($model->getTable());
    }

    public function scopeSuccess($query)
    {
        return $query->where('status', 'success');
    }

    public function scopeError($query)
    {
        return $query->where('status', 'error');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeRecent($query, int $days = 60)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeOlderThan($query, int $days)
    {
        return $query->where('created_at', '<', now()->subDays($days));
    }

    public static function cleanupOldLogs(int $days = 60): int
    {
        return static::olderThan($days)->delete();
    }
}
