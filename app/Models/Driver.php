<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'phone', 'licence_number', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
