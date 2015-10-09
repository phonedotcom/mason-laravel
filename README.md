## Mason Laravel

This project provides tools for building [Mason](https://github.com/JornWildt/Mason) hypermedia API's with [Laravel](http://laravel.com) or [Lumen](http://lumen.laravel.com).  At present, it includes a handful of utilities as described below. 

*NOTE: Better documentation is forthcoming. Meanwhile, please read the source code to learn more.*


## Motivation

[Mason](https://github.com/JornWildt/Mason) is a Hypermedia RESTful API format first published in 2013 by [Jørn Wildt](https://github.com/JornWildt). It combines lessons learned from years of API implementations and close study of other competing formats such as Hal, Collection+JSON, and JSON-LD.  Each format has its strengths, but none has the desired balance of simplicity and features.

Mason-Laravel was built to help promote Mason adoption within the Laravel and Lumen communities, and to provide much needed tooling.

## Installation

This project is integrated with [Composer](https://getcomposer.org/).  You can put it in your `composer.json` as a dependency:

```
"require": {
    "phonecom/mason-laravel": "0.*"
}
```

## Usage

###MasonCollection

This is a Mason Builder extension for producing lists of objects. Since Mason does not define how this should be done, we've come up with our own Mason-compatible way.  It features:

 * Pagination
 * Input validation
 * Unlimited data sources, with out-of-the-box support for:
   * Laravel's `Collection` container
   * Laravel's `Eloquent` queries
 * Filtering
 * Sorting
 * User-defined item renderers

The `MasonCollection` class is built to minimize the amount of code that would be needed inside a controller action.  Here's an example:

```
use Models\Vehicles;
use PhoneCom\Mason\Builder\Contrib\MasonCollection;

...

$query = Vehicles::where('type', 'used');
$container = new MasonCollection\EloquentContainer($query);
$document = new MasonCollection($request, $container);

$document->setFilterTypes(['make', 'model', 'year'])
    ->setSortTypes(['id', 'year'], ['id' => 'desc'])
    ->setControl('home', '/')
    ->assemble();
    
echo json_encode($document);
```

This would produce the following data structure, assuming the request is made without any input arguments:

```
{
    "total": 193,
    "offset": 0,
    "limit": 10,
    "items": [{
        "id": 10,
        "make": "Ford",
        "model": "F-250",
        "year": 1999
    }, {
        "id": 9,
        "make": "Honda",
        "model": "Accord",
        "year": 2013
    }, {
        "id": 8,
        "make": "Nissan",
        "model": "Altima",
        "year": 2008
    },
        ...
    ],
    "@controls": {
        "first": {
            "href": "/used-cars?offset=0"
        },
        "next": {
            "href": "/used-cars?offset=10"
        },
        "last": {
            "href": "/used-cars?offset=190"
        }
    }
}
```

More documentation is coming!  Most of the public methods are documented, so have a look at the source for more info.

###MasonServiceTrait

This is a trait you can add to your controller classes to ease the process of defining your REST API routes and to provide some consistent structure to your controllers and action methods.  It assumes each controller class will represent **one** route pattern and HTTP verb.

`MasonServiceTrait` provides a convenient way of registering routes.  In your `app/Http/routes.php`, you can use this for example:

```
UsedCars\GetCollection::registerRoute($app);
```

In your controller, you would define the route's properties. The action method should be named `action()`.  You can also optionally define two controller methods for the schemas of your inputs and outputs.  Here is an example:

```
<?php 
namespace App\Http\Controllers\UsedCars;

use App\Http\Controller;
use PhoneCom\Mason\Routing\MasonServiceTrait;

class GetCollection extends Controller
{
    use MasonServiceTrait;
    
    protected static $verb = 'GET';
    protected static $routeName = 'usedCars';
    protected static $routePath = '/used-cars';

    public function action()
    {
        ...
        
    }
    
    public function inputSchema()
    {
        ...
        
    }
    
    public function outputSchema()
    {
        ...
        
    }
}
```

More documentation is coming!  Have a look at the source for more info.

###MasonResponse

We have also included a `MasonResponse` class which helps to produce a Laravel response object you can return from your controller actions.  It features the following:

* Automatically responds to Mason's `Prefer: representation=minimal` request header
* Respects `Etag` caching by adding this header on all responses and responding appropriately to `If-None-Match` request headers
* Executes the `assemble()` method if the input is a `MasonCollection`
* Adds the Mason `Content-Type` response header
* Sorts the output properties in a logical manner which attempts to place your data higher up in the document ("above the fold") and leaves the Mason housekeeping items further down.

Here's an example:

```
<?php
namespace App\Http\Controllers;

use PhoneCom\Mason\Http\MasonResponse;
use Illuminate\Http\Request;

class MyController extends Controller
{
    public function myActionMethod(Request $request)
    {
        $masonDocument = ...
        
        return MasonResponse::create($masonDocument, $request);
    }
}
```

More documentation is coming!  Have a look at the source for more info.

###SchemaResponse

Similar to `MasonResponse`, we have included a `SchemaResponse` class which supports outputting JSON Schema documents built with the `JsonSchema` class from the [phonecom/mason-php](https://github.com/Phone-com/mason-php) project. Usage looks like this:

```
<?php
namespace App\Http\Controllers;

use PhoneCom\Mason\Http\SchemaResponse;
use PhoneCom\Mason\Schema\JsonSchema;
use Illuminate\Http\Request;

class MyController extends Controller
{
    public function mySchema(Request $request)
    {
        $schema = JsonSchema::make([
            ...
        ]);
        
        return SchemaResponse::create($schema, $request);
    }
}
```

More documentation is coming!  Have a look at the source for more info.

## Tests

Tests are forthcoming.

## Contributors
This project was created and is managed by [Phone.com](https://www.phone.com). We're building a hot new Hypermedia API and we chose Mason!

Pull requests are welcome.

## License

This project is released under the MIT License. Copyright (c) 2015 [Phone.com, Inc.](https://www.phone.com) See `LICENSE` for full details.
