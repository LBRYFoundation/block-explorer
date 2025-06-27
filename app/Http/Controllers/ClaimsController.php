<?php
namespace App\Http\Controllers;

use App\Models\Claim;

use Illuminate\Routing\Controller;

class ClaimsController extends Controller{

    public function apibrowse(): array{
        $pageLimit = 48;
        $beforeId = intval(request()->query('before'));
        $afterId = intval(request()->query('after'));
        $sort = trim(request()->query('sort'));
        $nsfw = trim(request()->query('nsfw'));

        switch ($sort) {
            case 'popular':
                // TODO: sort by upvote/downvote score
                break;
            case 'random':
                $order = 'RAND() ASC';
                break;
            case 'oldest':
                $order = 'created_at ASC';
                break;
            case 'newest':
            default:
                $order = 'created_at DESC';
                break;
        }

        //$stmt = $conn->execute('SELECT COUNT(Id) AS Total FROM Claims WHERE ThumbnailUrl IS NOT NULL AND LENGTH(TRIM(ThumbnailUrl)) > 0');
        //$count = $stmt->fetch(\PDO::FETCH_OBJ);
        $numClaims = 23000000;

        if ($beforeId < 0) {
            $beforeId = 0;
        }

        $conditions = [
            ['thumbnail_url','IS','NOT NULL'],
            ['LENGTH(TRIM(thumbnail_url))','>',0],
            ['is_filtered','<>',1],
        ];
        if ($afterId > 0) {
            $conditions[] = ['id','>',$afterId];
        } else if ($beforeId) {
            $conditions[] = ['id','<',$beforeId];
        }

        if ($nsfw !== 'true') {
            $conditions[] = ['is_nsfw','<>',1];
        }

        //->contain(['Stream', 'Publisher' => ['fields' => ['Name']]])
        $claims = Claim::query()->distinct(['claim_id'])->where($conditions)->limit($pageLimit)->orderByRaw($order)->get();

        return [
            'success' => true,
            'claims' => $claims,
            'total' => (int) $numClaims,
        ];
    }
}
