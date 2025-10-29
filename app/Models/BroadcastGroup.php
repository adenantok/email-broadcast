<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BroadcastGroup extends Model
{
    use HasFactory;

    protected $table = 'broadcast_groups';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'description',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = Str::uuid();
            }
        });
    }

    /**
     * Recipients in this group (Many-to-Many)
     */
    public function recipients()
    {
        return $this->belongsToMany(
            BroadcastRecipient::class,
            'broadcast_group_recipient',
            'group_id',
            'recipient_id'
        )->withTimestamps();
    }

    /**
     * Get count of recipients
     */
    public function getRecipientsCountAttribute()
    {
        return $this->recipients()->count();
    }
}