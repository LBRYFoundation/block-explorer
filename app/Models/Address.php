<?php
namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin Model
 * @property int Id
 * @property string Address
 * @property datetime FirstSeen
 * @property decimal TotalReceived
 * @property decimal TotalSent
 * @property decimal Balance
 * @property string Tag
 * @property string TagUrl
 * @property datetime Created
 * @property datetime Modified
 */
class Address extends Model{

    protected $fillable = [
        'Address',
        'FirstSeen',
    ];
    protected $table = 'Addresses';
    public $timestamps = false;

    public function __construct(array $attributes = []){
        parent::__construct($attributes);

        //TODO Fix default non-null attributes
        $this->Tag = '';
        $this->TagUrl = '';
        $this->Created = Carbon::now();
        $this->Modified = Carbon::now();
    }

}
