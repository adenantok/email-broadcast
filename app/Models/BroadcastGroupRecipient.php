<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BroadcastGroupRecipient extends Model
{
    protected $table = 'broadcast_group_recipient';
    protected $fillable = ['group_id', 'recipient_id'];

    public $incrementing = false;
    protected $keyType = 'string';

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }
}