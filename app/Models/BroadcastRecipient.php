<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class BroadcastRecipient extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'nama_perusahaan',
        'pic',
        'email',
        'is_subscribed',
        'last_sent_at',
        'sent_count'
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    protected static function booted()
    {
        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = Str::uuid();
            }
        });
    }
}
