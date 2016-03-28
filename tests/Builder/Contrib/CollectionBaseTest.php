<?php
namespace Tests\Builder\Contrib;

use Phonedotcom\Mason\Builder\Child;
use App\Models\Voip;
use App\Models\Voip\Sms;
use Illuminate\Http\Request;
use Phonedotcom\Mason\Builder\Contrib\MasonCollection;
use Tests\Integration\TestCase;

class CollectionBaseTest extends TestCase
{
    public function testCanGenerateResponse()
    {
        $request = Request::create('/', 'GET', []);
        $query = Sms::where('voip_id', 1);

        $doc = MasonCollection::make()
            ->populate($request, $query);

        $this->assertEquals(10, count($doc->items));
    }

    public function testCanUseCustomItemRendering()
    {
        $request = Request::create('/', 'GET');
        $query = Sms::where('voip_id', 1);

        $doc = MasonCollection::make()
            ->setItemRenderer(function (Request $request, Child $childDoc, Sms $sms) {
                $childDoc->setProperty('the_big_id', $sms->id);
            })
            ->populate($request, $query);

        $this->assertNotEmpty($doc->items[0]->the_big_id);
    }
}
