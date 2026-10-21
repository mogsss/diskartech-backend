<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeeklyScheduleSlot extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['ends_next_day' => 'boolean'];
}
