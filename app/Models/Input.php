<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @mixin Model
 */
class Input extends Model{

    protected $fillable = [];
    protected $table = 'Inputs';
    public $timestamps = false;

    public function input_addresses(): BelongsToMany{
        return $this->belongsToMany(Address::class,'InputsAddresses','InputId','AddressId');
    }

}
