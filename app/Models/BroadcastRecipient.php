<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class BroadcastRecipient extends Model
{
    use HasFactory;

    protected $table = 'broadcast_recipients';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'nama_perusahaan',
        'pic',
        'email',
        'is_subscribed',
        'status',
        'unsubscribed_at',
        'last_sent_at',
        'sent_count',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = Str::uuid();
            }
        });
    }

    // Relasi ke log unsubscribe
    public function unsubscribeLogs()
    {
        return $this->hasMany(BroadcastUnsubscribeLog::class, 'recipient_id');
    }
}
