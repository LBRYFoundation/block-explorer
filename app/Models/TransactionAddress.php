<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin Model
 */
class TransactionAddress extends Model{

    protected $fillable = [];
    protected $table = 'TransactionAddresses';
    public $timestamps = false;

}
