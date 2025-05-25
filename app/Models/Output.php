<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @mixin Model
 */
class Output extends Model{

    protected $fillable = [];
    protected $table = 'Outputs';
    public $timestamps = false;

    public function output_addresses(): BelongsToMany{
        return $this->belongsToMany(Address::class,'OutputsAddresses','OutputId','AddressId');
    }

    public function spend_input(): BelongsTo{
        return $this->belongsTo(Input::class,'SpentByInputId','Id');
    }

}
