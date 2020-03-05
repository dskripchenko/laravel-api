## Installation

```
$ php composer.phar require dskripchenko/laravel-api "@dev"
```

or add

```
"dskripchenko/laravel-api": "@dev"
```

to the ```require``` section of your `composer.json` file.


## Usage
See ```Dskripchenko\LaravelApiExample\```

* make `ApiModule` extended from `Dskripchenko\LaravelApi\Components\BaseModule`
* make `Api` extended from `Dskripchenko\LaravelApi\Components\BaseApi`
* define `getMethods` method
* override `getApiVersionList` in `ApiModule`, return array of `['version' => Api::class]`
* make `ApiServiceProvider` extended from `Dskripchenko\LaravelApi\ApiServiceProvider`
* override `getApiModule` in new `ApiServiceProvider`
* register `ApiServiceProvider`


## Api methods template
```php
protected static function getMethods(){
    return [
      'controllers' => [
        '{controllesKey1}' => [
            'controller' => '{Controller::class}',
            'actions' => [
                '{actionKey1}', // api/version/{controllesKey1}/{actionKey1}
                '{actionKey2}' => '{real method name}', //api/version/{controllesKey1}/{actionKey2}
                '{actionKey3}' => false, //disable //api/version/{controllesKey1}/{actionKey3}
                '{actionKey4}' => [
                    'exclude-all-middleware' => true, //optional, exclude all global and controller middleware
                    'middleware' => [
                        "{Middleware::class}"
                    ], //optional, define specific middleware 
                    'exclude-middleware' => [], //optional, exclude specific middleware
                ],
            ],
            'exclude-all-middleware' => true, //optional, ...
            'middleware' => [], //optional, ...
            'exclude-middleware' => [], //optional, ...
        ]
      ],
      'middleware' => [] //optional, ...
    ];
}

```

## AutoDoc comments to swagger
```php
/**
 * Method title
 * Method description
 *
 * @input type $requiredVariable1 name1
 * @input type ?$optionalVariable2 name2
 *
 * @output type $variable1 name1
 * @output type $variable2 name2
 */

```
Available types ```string|number|integer|boolean|file```

## Facades
* ApiRequest
    * getApiVersion
    * getApiMethod
    * getApiControllerKey
    * getApiActionKey
* ApiModule
    * getApiPrefix
    * getAvailableApiMethods
    * getApiUriPattern
    * getControllerNamespace
    * getApiMiddleware
    * getApiVersionList
    * makeApi
* ApiErrorHandler
    * addErrorHandler
    * handle

## Helpers 
* ApiResponseHelper
    * say
    * sayError
* ArrayMergeHelper
    * merge //deep array merge

## Example

* register `Dskripchenko\LaravelApiExample\ExampleApiServiceProvider`
* run `php artisan vendor:publish`
* run `php artisan storage:link`
* open `/api/doc`