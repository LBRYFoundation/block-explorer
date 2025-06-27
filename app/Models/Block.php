<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin Model
 * @property mixed id
 * @property mixed bits
 * @property mixed chainwork
 * @property mixed confirmations
 * @property mixed difficulty
 * @property mixed hash
 * @property mixed height
 * @property mixed merkle_root
 * @property mixed name_claim_root
 * @property mixed nonce
 * @property mixed previous_block_hash
 * @property mixed next_block_hash
 * @property mixed block_size
 * @property mixed block_time
 * @property mixed version
 * @property mixed version_hex
 * @property mixed tx_count
 * @property mixed created_at
 * @property mixed modified_at
 */
class Block extends Model{

    protected $table = 'block';
    public $timestamps = false;

    public function jsonSerialize(): array{
        return [
            'height' => $this->height,
            'block_time' => $this->block_time,
            'tx_count' => $this->tx_count,
        ];
    }

}
