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
                $order = 'Created ASC';
                break;
            case 'newest':
            default:
                $order = 'Created DESC';
                break;
        }

        //$stmt = $conn->execute('SELECT COUNT(Id) AS Total FROM Claims WHERE ThumbnailUrl IS NOT NULL AND LENGTH(TRIM(ThumbnailUrl)) > 0');
        //$count = $stmt->fetch(\PDO::FETCH_OBJ);
        $numClaims = 23000000;

        if ($beforeId < 0) {
            $beforeId = 0;
        }

        $conditions = [
            ['ThumbnailUrl','IS','NOT NULL'],
            ['LENGTH(TRIM(ThumbnailUrl))','>',0],
            ['IsFiltered','<>',1],
        ];
        if ($afterId > 0) {
            $conditions[] = ['Id','>',$afterId];
        } else if ($beforeId) {
            $conditions[] = ['Id','<',$beforeId];
        }

        if ($nsfw !== 'true') {
            $conditions[] = ['IsNSFW','<>',1];
        }

        //->contain(['Stream', 'Publisher' => ['fields' => ['Name']]])
        $claims = Claim::query()->distinct(['ClaimId'])->where($conditions)->limit($pageLimit)->orderByRaw($order)->get();

        return [
            'success' => true,
            'claims' => $claims,
            'total' => (int) $numClaims,
        ];
    }
}
