<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin Model
 */
class Transaction extends Model{

    protected $fillable = [
        'Version',
    ];
    protected $table = 'Transactions';
    public $timestamps = false;

}
