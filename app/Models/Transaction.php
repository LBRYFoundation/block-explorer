<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin Model
 * @property mixed id
 * @property mixed block_hash_id
 * @property mixed input_count
 * @property mixed output_count
 * @property mixed transaction_time
 * @property mixed transaction_size
 * @property mixed hash
 * @property mixed version
 * @property mixed lock_time
 * @property mixed created_at
 * @property mixed modified_at
 * @property mixed created_time
 * @property mixed value
 */
class Transaction extends Model{

    protected $table = 'transaction';
    public $timestamps = false;

}
