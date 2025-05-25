<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin Model
 * @property mixed Bits
 * @property mixed Chainwork
 * @property mixed Confirmations
 * @property mixed Difficulty
 * @property mixed Hash
 * @property mixed Height
 * @property mixed MedianTime
 * @property mixed MerkleRoot
 * @property mixed NameClaimRoot
 * @property mixed Nonce
 * @property mixed PreviousBlockHash
 * @property mixed NextBlockHash
 * @property mixed BlockSize
 * @property mixed Target
 * @property mixed BlockTime
 * @property mixed TransactionHashes
 * @property mixed Version
 * @property mixed VersionHex
 */
class Block extends Model{

    protected $fillable = [
        'Bits',
        'Chainwork',
        'Confirmations',
        'Difficulty',
        'Hash',
        'Height',
        'MedianTime',
        'MerkleRoot',
        'NameClaimRoot',
        'Nonce',
        'PreviousBlockHash',
        'NextBlockHash',
        'BlockSize',
        'Target',
        'BlockTime',
        'TransactionHashes',
        'Version',
        'VersionHex',
    ];
    protected $table = 'Blocks';
    public $timestamps = false;

    public function jsonSerialize(): array{
        return [
            'height' => $this->Height,
            'block_time' => $this->BlockTime,
            'tx_count' => -1,//TODO
        ];
    }

}
