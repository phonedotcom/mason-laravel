<?php
namespace Tests\Builder\Contrib;

use PhoneCom\Mason\Builder\Child;
use App\Models\Voip;
use App\Models\Voip\Sms;
use Illuminate\Http\Request;
use PhoneCom\Mason\Builder\Contrib\MasonCollection;
use Tests\Integration\TestCase;

class CollectionBaseTest extends TestCase
{
    public function testCanGenerateResponse()
    {
        $request = Request::create('/', 'GET', []);
        $query = Sms::where('voip_id', 1);

        $doc = (new MasonCollection($request, $query))->assemble();

        $this->assertEquals(10, count($doc->items));
    }

    public function testCanUseCustomItemRendering()
    {
        $request = Request::create('/', 'GET');
        $query = Sms::where('voip_id', 1);

        $doc = (new MasonCollection($request, $query))
            ->setItemRenderer(function (Child $childDoc, Sms $sms) {
                $childDoc->setProperty('the_big_id', $sms->id);
            })
            ->assemble();

        $this->assertNotEmpty($doc->items[0]->the_big_id);
    }
}
