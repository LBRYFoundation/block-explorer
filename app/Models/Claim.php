<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin Model
 * @property mixed id
 * @property mixed transaction_hash_id
 * @property mixed vout
 * @property mixed name
 * @property mixed claim_id
 * @property mixed claim_type
 * @property mixed publisher_id
 * @property mixed publisher_sig
 * @property mixed certificate
 * @property mixed sd_hash
 * @property mixed transaction_time
 * @property mixed version
 * @property mixed value_as_hex
 * @property mixed value_as_json
 * @property mixed valid_at_height
 * @property mixed height
 * @property mixed effective_amount
 * @property mixed author
 * @property mixed description
 * @property mixed content_type
 * @property mixed is_nsfw
 * @property mixed language
 * @property mixed thumbnail_url
 * @property mixed title
 * @property mixed fee
 * @property mixed fee_currency
 * @property mixed fee_address
 * @property mixed is_filtered
 * @property mixed bid_state
 * @property mixed created_at
 * @property mixed modified_at
 * @property mixed claim_address
 * @property mixed is_cert_valid
 * @property mixed is_cert_processed
 * @property mixed license
 * @property mixed type
 * @property mixed release_time
 * @property mixed source_hash
 * @property mixed source_name
 * @property mixed source_size
 * @property mixed source_media_type
 * @property mixed source_url
 * @property mixed frame_width
 * @property mixed frame_height
 * @property mixed duration
 * @property mixed audio_duration
 * @property mixed email
 * @property mixed has_claim_list
 * @property mixed claim_reference
 * @property mixed list_type
 * @property mixed claim_id_list
 * @property mixed transaction_hash_update
 * @property mixed vout_update
 * @property mixed claim_count
 */
class Claim extends Model{

    protected $casts = [
        'created_at' => 'datetime',
    ];
    protected $table = 'claim';
    public $timestamps = false;

//    public function publisher(): BelongsTo{
//        return $this->belongsTo(Claim::class, 'publisher_id','claim_id');
//    }

    public function getLbryLink(): string{
        $link = $this->name;
        if (isset($this->publisher)) {
            $link = $this->publisher . '/' . $link;
        }
        $link = 'lbry://' . $link;
        return $link;
    }

    public function getExplorerLink(): string{
        $link = '/claims/' . $this->claim_id;
        return $link;
    }

    public function getContentTag(): ?string{
        $ctTag = null;
        if (substr($this->content_type, 0, 5) === 'audio') {
            $ctTag = 'audio';
        } else if (substr($this->content_type, 0, 5) === 'video') {
            $ctTag = 'video';
        } else if (substr($this->content_type, 0, 5) === 'image') {
            $ctTag = 'image';
        }

        if (!$ctTag && $this->claim_type == 2) {
            $ctTag = 'identity';
        }
        return $ctTag;
    }

    public function getAutoThumbText(): string{
        $autoThumbText = '';
        if ($this->claim_type == 2) {
            $autoThumbText = strtoupper(substr($this->name, 1, min(strlen($this->name), 10)));
        } else {
            $str = (strlen(trim($this->title)) > 0) ? $this->title : $this->name;
            $autoThumbText = strtoupper(substr($str, 0, min(strlen($str), 5)));
        }
        return $autoThumbText;
    }

}
