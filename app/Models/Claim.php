<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin Model
 */
class Claim extends Model{

    protected $fillable = [];
    protected $table = 'Claims';
    public $timestamps = false;

    public function publisher(): BelongsTo{
        return $this->belongsTo(Claim::class, 'PublisherId','ClaimId');
    }

    function getLbryLink() {
        $link = $this->name;
        if (isset($this->publisher)) {
            $link = $this->publisher . '/' . $link;
        }
        $link = 'lbry://' . $link;
        return $link;
    }

    function getExplorerLink() {
        $link = '/claims/' . $this->claim_id;
        return $link;
    }

    function getContentTag() {
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

    function getAutoThumbText() {
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
