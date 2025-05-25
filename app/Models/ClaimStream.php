<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin Model
 */
class ClaimStream extends Model{

    protected $fillable = [];
    protected $table = 'ClaimStreams';
    public $timestamps = false;

}
