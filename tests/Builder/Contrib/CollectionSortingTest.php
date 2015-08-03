<?php
namespace Tests\Builder\Contrib;

use App\Models\Voip;
use App\Models\Voip\Sms;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use PhoneCom\Mason\Builder\Contrib\MasonCollection;
use Tests\Integration\TestCase;

class CollectionSortingTest extends TestCase
{
    public function testCanSetDefaultSorting()
    {
        $request = Request::create('/', 'GET');
        $query = Sms::where('voip_id', 1);

        $doc = (new MasonCollection($request, $query))
            ->setSortTypes(['scheduled', 'created'], ['created' => 'desc'])
            ->assemble();

        $firstTimestamp = $doc->items[0]->created;
        $lastTimestamp = $doc->items[7]->created;
        $this->assertLessThan($firstTimestamp, $lastTimestamp);
    }

    public function testCanSortAsc()
    {
        $request = Request::create('/', 'GET', ['sort' => ['created' => 'asc']]);
        $query = Sms::where('voip_id', 1);

        $doc = (new MasonCollection($request, $query))
            ->setSortTypes(['created'])
            ->assemble();

        $firstTimestamp = $doc->items[0]->created;
        $lastTimestamp = $doc->items[7]->created;
        $this->assertGreaterThan($firstTimestamp, $lastTimestamp);
    }

    /**
     * @expectedException \Illuminate\Contracts\Validation\ValidationException
     */
    public function testInvalidSortDirectionFails()
    {
        $request = Request::create('/', 'GET', ['sort' => ['created' => 'yak']]);
        $query = Sms::where('voip_id', 1);

        (new MasonCollection($request, $query))
            ->setSortTypes(['created'])
            ->assemble();
    }

    public function testCanSortDesc()
    {
        $request = Request::create('/', 'GET', ['sort' => ['created' => 'desc']]);
        $query = Sms::where('voip_id', 1);

        $doc = (new MasonCollection($request, $query))
            ->setSortTypes(['created'])
            ->assemble();

        $firstTimestamp = $doc->items[0]->created;
        $lastTimestamp = $doc->items[7]->created;
        $this->assertLessThan($firstTimestamp, $lastTimestamp);
    }

    public function testCanSortByCustomClosure()
    {
        $request = Request::create('/', 'GET', ['sort' => ['pogo' => 'desc']]);
        $query = Sms::where('voip_id', 1);

        $doc = (new MasonCollection($request, $query))
            ->setSortTypes(['pogo' => function (Builder $query, $direction) {
                $query->orderBy('created', $direction);
            }])
            ->assemble();

        $firstCreated = $doc->items[0]->created;
        $lastCreated = $doc->items[7]->created;
        $this->assertLessThan($firstCreated, $lastCreated);
    }
}
