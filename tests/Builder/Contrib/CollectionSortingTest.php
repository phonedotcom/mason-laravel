<?php
namespace Tests\Builder\Contrib;

use App\Models\Voip;
use App\Models\Voip\Sms;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use PhoneCom\Mason\Builder\Contrib\MasonCollection;
use PhoneCom\Mason\Builder\Contrib\MasonCollection\Sort;

class CollectionSortingTest extends TestCase
{
    public function testCanSetDefaultSorting()
    {
        $request = Request::create('/', 'GET');
        $query = Sms::where('voip_id', 1);

        $doc = MasonCollection::make()
            ->setSortTypes(['scheduled', 'created'], ['created' => 'desc'])
            ->populate($request, $query);

        $firstTimestamp = $doc->items[0]->created;
        $lastTimestamp = $doc->items[7]->created;
        $this->assertLessThan($firstTimestamp, $lastTimestamp);
    }

    public function testCanSortAsc()
    {
        $request = Request::create('/', 'GET', ['sort' => ['created' => 'asc']]);
        $query = Sms::where('voip_id', 1);

        $doc = MasonCollection::make()
            ->setSortTypes(['created'])
            ->populate($request, $query);

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

        MasonCollection::make()
            ->setSortTypes(['created'])
            ->populate($request, $query);
    }

    public function testCanSortDesc()
    {
        $request = Request::create('/', 'GET', ['sort' => ['created' => 'desc']]);
        $query = Sms::where('voip_id', 1);

        $doc = MasonCollection::make()
            ->setSortTypes(['created'])
            ->populate($request, $query);

        $firstTimestamp = $doc->items[0]->created;
        $lastTimestamp = $doc->items[7]->created;
        $this->assertLessThan($firstTimestamp, $lastTimestamp);
    }

    public function testCanSortByCustomClosure()
    {
        $request = Request::create('/', 'GET', ['sort' => ['pogo' => 'desc']]);
        $query = Sms::where('voip_id', 1);

        $doc = MasonCollection::make()
            ->setSortTypes([
                Sort::make('pogo')->setFunction(function (Builder $query, $direction) {
                    $query->orderBy('created', $direction);
                })
            ])
            ->populate($request, $query);

        $firstCreated = $doc->items[0]->created;
        $lastCreated = $doc->items[7]->created;
        $this->assertLessThan($firstCreated, $lastCreated);
    }
}
