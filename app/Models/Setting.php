<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Raw key/value row. Read and write settings through App\Support\Settings (or the setting() helper). */
class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];
}
