<?php
namespace Tests\Integration\Libraries\Mason;

use App\Models\Voip;
use App\Models\Voip\Sms;
use Illuminate\Http\Request;
use PhoneCom\Mason\Builder\Contrib\MasonCollection;
use Tests\Integration\TestCase;

class CollectionFilteringTest extends TestCase
{
    public function testCanGenerateResponse()
    {
        $request = Request::create('/', 'GET', []);
        $query = Sms::where('voip_id', 1);

        $doc = MasonCollection::make()
            ->populate($request, $query);

        $this->assertEquals(10, count($doc->items));
    }

    public function testCanFilterWithSingleCriteriaPerType()
    {
        $request = Request::create('/', 'GET', ['filter' => ['content' => 'not-empty']]);
        $query = Sms::where('voip_id', 1);

        $doc = MasonCollection::make()
            ->setFilterTypes(['content'])
            ->populate($request, $query);

        $this->assertEquals(10, count($doc->items));
    }

    public function testCanFilterWithMultipleCriteriaPerType()
    {
        $request = Request::create('/', 'GET', ['filter' => ['content' => ['not-empty', 'contains:president']]]);
        $query = Sms::where('voip_id', 1);

        $doc = MasonCollection::make()
            ->setFilterTypes(['content'])
            ->populate($request, $query);

        $this->assertEquals(1, count($doc->items));
    }

    public function testCanUseEmptyFilter()
    {
        $request = Request::create('/', 'GET', ['filter' => ['scheduled' => 'empty']]);
        $query = Sms::where('voip_id', 1);

        $doc = MasonCollection::make()
            ->setFilterTypes(['scheduled'])
            ->populate($request, $query);

        $this->assertEquals(6, count($doc->items));
    }

    public function testCanUseNotEmptyFilter()
    {
        $request = Request::create('/', 'GET', ['filter' => ['scheduled' => 'not-empty']]);
        $query = Sms::where('voip_id', 1);

        $doc = MasonCollection::make()
            ->setFilterTypes(['scheduled'])
            ->populate($request, $query);

        $this->assertEquals(4, count($doc->items));
    }

    public function testCanUseEqualsFilter()
    {
        $request = Request::create('/', 'GET', ['filter' => ['content' => 'eq:Hello world!']]);
        $query = Sms::where('voip_id', 1);

        $doc = MasonCollection::make()
            ->setFilterTypes(['content'])
            ->populate($request, $query);

        $this->assertEquals(2, count($doc->items));
    }

    public function testCanUseNotEqualsFilter()
    {
        $request = Request::create('/', 'GET', ['filter' => ['content' => 'ne:Hello world!']]);
        $query = Sms::where('voip_id', 1);

        $doc = MasonCollection::make()
            ->setFilterTypes(['content'])
            ->populate($request, $query);

        $this->assertEquals(8, count($doc->items));
    }

    public function testCanUseLessThanFilter()
    {
        $request = Request::create('/', 'GET', ['filter' => [
            'created' => 'lt:' . strtotime('Apr 30, 2015 2:35:02 PM')
        ]]);
        $query = Sms::where('voip_id', 1);

        $doc = MasonCollection::make()
            ->setFilterTypes(['created'])
            ->populate($request, $query);

        $this->assertEquals(3, count($doc->items));
    }

    public function testCanUseLessThanOrEqualsFilter()
    {
        $request = Request::create('/', 'GET', ['filter' => [
            'created' => 'lte:' . strtotime('Apr 30, 2015 2:35:02 PM')
        ]]);
        $query = Sms::where('voip_id', 1);

        $doc = MasonCollection::make()
            ->setFilterTypes(['created'])
            ->populate($request, $query);

        $this->assertEquals(4, count($doc->items));
    }

    public function testCanUseGreaterThanFilter()
    {
        $request = Request::create('/', 'GET', ['filter' => [
            'created' => 'gt:' . strtotime('Apr 30, 2015 2:35:02 PM')
        ]]);
        $query = Sms::where('voip_id', 1);

        $doc = MasonCollection::make()
            ->setFilterTypes(['created'])
            ->populate($request, $query);

        $this->assertEquals(6, count($doc->items));
    }

    public function testCanUseGreaterThanOrEqualsFilter()
    {
        $request = Request::create('/', 'GET', ['filter' => [
            'created' => 'gte:' . strtotime('Apr 30, 2015 2:35:02 PM')
        ]]);
        $query = Sms::where('voip_id', 1);

        $doc = MasonCollection::make()
            ->setFilterTypes(['created'])
            ->populate($request, $query);

        $this->assertEquals(7, count($doc->items));
    }

    public function testCanUseStartsWithFilter()
    {
        $request = Request::create('/', 'GET', ['filter' => ['content' => 'starts-with:Whatever']]);
        $query = Sms::where('voip_id', 1);

        $doc = MasonCollection::make()
            ->setFilterTypes(['content'])
            ->populate($request, $query);

        $this->assertEquals(1, count($doc->items));
    }

    public function testCanUseEndsWithFilter()
    {
        $request = Request::create('/', 'GET', ['filter' => ['content' => 'ends-with:rock']]);
        $query = Sms::where('voip_id', 1);

        $doc = MasonCollection::make()
            ->setFilterTypes(['content'])
            ->populate($request, $query);

        $this->assertEquals(1, count($doc->items));
    }

    public function testCanUseContainsFilter()
    {
        $request = Request::create('/', 'GET', ['filter' => ['content' => 'contains:love']]);
        $query = Sms::where('voip_id', 1);

        $doc = MasonCollection::make()
            ->setFilterTypes(['content'])
            ->populate($request, $query);

        $this->assertEquals(2, count($doc->items));
    }

    public function testCanUseNotStartsWithFilter()
    {
        $request = Request::create('/', 'GET', ['filter' => ['content' => 'not-starts-with:Whatever']]);
        $query = Sms::where('voip_id', 1);

        $doc = MasonCollection::make()
            ->setFilterTypes(['content'])
            ->populate($request, $query);

        $this->assertEquals(9, count($doc->items));
    }

    public function testCanUseNotEndsWithFilter()
    {
        $request = Request::create('/', 'GET', ['filter' => ['content' => 'not-ends-with:rock']]);
        $query = Sms::where('voip_id', 1);

        $doc = MasonCollection::make()
            ->setFilterTypes(['content'])
            ->populate($request, $query);

        $this->assertEquals(9, count($doc->items));
    }

    public function testCanUseNotContainsFilter()
    {
        $request = Request::create('/', 'GET', ['filter' => ['content' => 'not-contains:love']]);
        $query = Sms::where('voip_id', 1);

        $doc = MasonCollection::make()
            ->setFilterTypes(['content'])
            ->populate($request, $query);

        $this->assertEquals(8, count($doc->items));
    }

    public function testCanUseBetweenFilter()
    {
        $request = Request::create('/', 'GET', ['filter' => [
            'created' => 'between:' . strtotime('Apr 30, 2015 2:35:02 PM') . ',' . strtotime('Apr 30, 2015 2:35:04 PM')
        ]]);
        $query = Sms::where('voip_id', 1);

        $doc = MasonCollection::make()
            ->setFilterTypes(['created'])
            ->populate($request, $query);

        $this->assertEquals(3, count($doc->items));
    }

    public function testCanUseNotBetweenFilter()
    {
        $request = Request::create('/', 'GET', ['filter' => [
            'created' => 'not-between:'
                . strtotime('Apr 30, 2015 2:35:02 PM')
                . ',' . strtotime('Apr 30, 2015 2:35:04 PM')
        ]]);
        $query = Sms::where('voip_id', 1);

        $doc = MasonCollection::make()
            ->setFilterTypes(['created'])
            ->populate($request, $query);

        $this->assertEquals(7, count($doc->items));
    }

    /**
     * @expectedException \Illuminate\Contracts\Validation\ValidationException
     */
    public function testInvalidFilterColumnFails()
    {
        $request = Request::create('/', 'GET', ['filter' => ['snake' => 'empty']]);
        $query = Sms::where('voip_id', 1);

        MasonCollection::make()
            ->setFilterTypes(['created'])
            ->populate($request, $query);
    }

    /**
     * @expectedException \Illuminate\Contracts\Validation\ValidationException
     */
    public function testInvalidFilterOperatorFails()
    {
        $request = Request::create('/', 'GET', ['filter' => ['created' => 'jumps']]);
        $query = Sms::where('voip_id', 1);

        MasonCollection::make()
            ->setFilterTypes(['created'])
            ->populate($request, $query);
    }

    /**
     * @expectedException \Illuminate\Contracts\Validation\ValidationException
     */
    public function testInvalidFilterParamCountFails()
    {
        $request = Request::create('/', 'GET', ['filter' => ['content' => 'equals:fifteen,planet']]);
        $query = Sms::where('voip_id', 1);

        MasonCollection::make()
            ->setFilterTypes(['content'])
            ->populate($request, $query);
    }

    public function testCanEscapeCommaInFilterParam()
    {
        $request = Request::create('/', 'GET', ['filter' => ['content' => 'contains:got it\, lol']]);
        $query = Sms::where('voip_id', 1);

        $doc = MasonCollection::make()
            ->setFilterTypes(['content'])
            ->populate($request, $query);

        $this->assertEquals(1, count($doc->items));
    }
}
