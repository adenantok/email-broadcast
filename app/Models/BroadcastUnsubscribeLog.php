<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class BroadcastUnsubscribeLog extends Model
{
    use HasFactory;

    protected $table = 'broadcast_unsubscribe_logs';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'recipient_id',
        'reason',
        'unsubscribed_at',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = Str::uuid();
            }
        });
    }

    public function recipient()
    {
        return $this->belongsTo(BroadcastRecipient::class, 'recipient_id');
    }
}
