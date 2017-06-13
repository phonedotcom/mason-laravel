<?php
namespace Tests\Builder\Contrib;

use App\Models\Voip;
use App\Models\Voip\Sms;
use Illuminate\Http\Request;
use Phonedotcom\Mason\Builder\Contrib\MasonCollection;
use Tests\Integration\TestCase;

class CollectionPaginationTest extends TestCase
{
    public function testCanSetPageSize()
    {
        $request = Request::create('/', 'GET', ['page_size' => 2]);
        $query = Sms::where('voip_id', 1);

        $doc = MasonCollection::make()
            ->populate($request, $query);

        $this->assertEquals(5, $doc->total_pages);
        $this->assertEquals(2, count($doc->items));
    }

    public function testCanSetPageNumber()
    {
        $request = Request::create('/', 'GET', ['page' => 1, 'page_size' => 2]);
        $query = Sms::where('voip_id', 1);

        $doc = MasonCollection::make()
            ->populate($request, $query);

        $firstPageFirstItemId = $doc->items[0]->id;

        $request = Request::create('/', 'GET', ['page' => 3, 'page_size' => 2]);
        $query = Sms::where('voip_id', 1);

        $doc = MasonCollection::make()
            ->populate($request, $query);

        $this->assertNotEquals($firstPageFirstItemId, $doc->items[0]->id);
    }

    /**
     * @expectedException \Illuminate\Validation\ValidationException
     */
    public function testHumongousPageSizeFails()
    {
        $request = Request::create('/', 'GET', ['page_size' => 9999999]);
        $query = Sms::where('voip_id', 1);

        MasonCollection::make()
            ->populate($request, $query);
    }

    /**
     * @expectedException \Illuminate\Validation\ValidationException
     */
    public function testNegativePageSizeFails()
    {
        $request = Request::create('/', 'GET', ['page_size' => -2]);
        $query = Sms::where('voip_id', 1);

        MasonCollection::make()
            ->populate($request, $query);
    }

    /**
     * @expectedException \Illuminate\Validation\ValidationException
     */
    public function testNegativePageNumberFails()
    {
        $request = Request::create('/', 'GET', ['page' => -2]);
        $query = Sms::where('voip_id', 1);

        MasonCollection::make()
            ->populate($request, $query);
    }

    public function testHumongousPageNumberGivesZeroItems()
    {
        $request = Request::create('/', 'GET', ['page' => 99999]);
        $query = Sms::where('voip_id', 1);

        $doc = MasonCollection::make()
            ->populate($request, $query);

        $this->assertEquals(0, count($doc->items));
    }

    public function testCanSetLimit()
    {
        $request = Request::create('/', 'GET', ['limit' => 3]);
        $query = Sms::where('voip_id', 1);

        $doc = MasonCollection::make()
            ->populate($request, $query);

        $this->assertEquals(3, $doc->limit);
        $this->assertEquals(3, count($doc->items));
    }

    public function testCanSetOffset()
    {
        $request = Request::create('/', 'GET', ['offset' => 0, 'limit' => 2]);
        $query = Sms::where('voip_id', 1);

        $doc = MasonCollection::make()
            ->populate($request, $query);

        $firstPageFirstItemId = $doc->items[0]->id;

        $request = Request::create('/', 'GET', ['offset' => 1, 'limit' => 2]);
        $query = Sms::where('voip_id', 1);

        $doc = MasonCollection::make()
            ->populate($request, $query);

        $this->assertNotEquals($firstPageFirstItemId, $doc->items[0]->id);
    }


    /**
     * @expectedException \Illuminate\Validation\ValidationException
     */
    public function testHumongousLimitFails()
    {
        $request = Request::create('/', 'GET', ['limit' => 9999999]);
        $query = Sms::where('voip_id', 1);

        MasonCollection::make()
            ->populate($request, $query);
    }

    /**
     * @expectedException \Illuminate\Validation\ValidationException
     */
    public function testNegativeLimitFails()
    {
        $request = Request::create('/', 'GET', ['limit' => -2]);
        $query = Sms::where('voip_id', 1);

        MasonCollection::make()
            ->populate($request, $query);
    }

    /**
     * @expectedException \Illuminate\Validation\ValidationException
     */
    public function testNegativeOffsetFails()
    {
        $request = Request::create('/', 'GET', ['offset' => -2]);
        $query = Sms::where('voip_id', 1);

        MasonCollection::make()
            ->populate($request, $query);
    }

    public function testHumongousOffsetGivesZeroItems()
    {
        $request = Request::create('/', 'GET', ['offset' => 99999]);
        $query = Sms::where('voip_id', 1);

        $doc = MasonCollection::make()
            ->populate($request, $query);

        $this->assertEquals(0, count($doc->items));
    }
}
