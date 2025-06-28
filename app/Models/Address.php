<?php
namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin Model
 * @property mixed id
 * @property mixed address
 * @property mixed first_seen
 * @property mixed created_at
 * @property mixed modified_at
 * @property mixed balance
 */
class Address extends Model{

    protected $casts = [
        'first_seen' => 'datetime',
    ];
    protected $table = 'address';
    public $timestamps = false;

}
