<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KycDocument extends Model
{
    protected $fillable = ['user_id', 'doc_type', 'file_path', 'file_data'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
