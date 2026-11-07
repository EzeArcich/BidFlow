<?php

namespace App\Domain\Shared\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

abstract class DomainModel extends Model
{
    use HasFactory, HasUlids;

    protected $guarded = ['id'];

    protected static function newFactory()
    {
        $class = 'Database\\Factories\\'.class_basename(static::class).'Factory';

        return $class::new();
    }
}
