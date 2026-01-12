<?php

namespace Darvis\UblPeppol\Models;

use Illuminate\Database\Eloquent\Model;

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
