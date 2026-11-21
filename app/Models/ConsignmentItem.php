<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsignmentItem extends Model
{
    protected $fillable = ['quantity', 'description', 'amount'];

    public function consignment() { return $this->belongsTo(Consignment::class); }
}
