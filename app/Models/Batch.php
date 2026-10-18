<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Batch extends Model
{
    use SoftDeletes;
    protected $guarded = [];

    protected function casts(): array
    {
        return ['template_snapshot' => 'array'];
    }

    public function template()
    {
        return $this->belongsTo(Template::class);
    }

    public function firstFailure()
    {
        return $this->hasOne(Certificate::class)->where('status', 'failed')->oldestOfMany();
    }

    public function certificates()
    {
        return $this->hasMany(Certificate::class);
    }
}
