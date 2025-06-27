<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @mixin Model
 * @property mixed id
 * @property mixed transaction_id
 * @property mixed transaction_hash
 * @property mixed value
 * @property mixed vout
 * @property mixed type
 * @property mixed script_pub_key_asm
 * @property mixed script_pub_key_hex
 * @property mixed required_signatures
 * @property mixed address_list
 * @property mixed is_spent
 * @property mixed spent_by_input_id
 * @property mixed created_at
 * @property mixed modified_at
 * @property mixed claim_id
 */
class Output extends Model{

    protected $table = 'output';
    public $timestamps = false;

    public function output_addresses(): BelongsToMany{
        return $this->belongsToMany(Address::class,'OutputsAddresses','OutputId','AddressId');
    }

    public function spend_input(): BelongsTo{
        return $this->belongsTo(Input::class,'SpentByInputId','Id');
    }

}
