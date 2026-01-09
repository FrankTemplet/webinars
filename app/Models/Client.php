<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $fillable = ['name', 'slug', 'logo'];

    public function webinars()
    {
        return $this->hasMany(Webinar::class);
    }
}
