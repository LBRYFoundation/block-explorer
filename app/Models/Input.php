<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @mixin Model
 * @property mixed id
 * @property mixed transaction_id
 * @property mixed transaction_hash
 * @property mixed input_address_id
 * @property mixed is_coinbase
 * @property mixed coinbase
 * @property mixed prevout_hash
 * @property mixed prevout_n
 * @property mixed sequence
 * @property mixed value
 * @property mixed script_sig_asm
 * @property mixed script_sig_hex
 * @property mixed created
 * @property mixed modified
 * @property mixed vin
 * @property mixed witness
 */
class Input extends Model{

    protected $table = 'input';
    public $timestamps = false;

    public function input_addresses(): BelongsToMany{
        return $this->belongsToMany(Address::class,'InputsAddresses','InputId','AddressId');
    }

}
