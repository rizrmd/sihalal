<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiHalal extends Model
{
    /**
     * The table name for the model.
     *
     * @var string
     */
    protected $table = 'si_halal_configurations';

    protected $fillable = [
        'api_key',
        'form_id',
        'bearer_token',
        'pelaku_usaha_uuid',
    ];
}
