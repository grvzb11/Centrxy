<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Certificate extends Model
{
    use SoftDeletes;
    protected $guarded = [];

    protected function casts(): array
    {
        return ['issued_at' => 'datetime'];
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }
}
