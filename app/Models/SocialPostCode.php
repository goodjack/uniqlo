<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialPostCode extends Model
{
    public $timestamps = false;

    protected $fillable = ['social_post_id', 'code', 'start_seconds'];
}
