<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin Model
 * @property mixed transaction_id
 * @property mixed address_id
 * @property mixed debit_amount
 * @property mixed credit_amount
 */
class TransactionAddress extends Model{

    protected $table = 'transaction_address';
    public $timestamps = false;

}
