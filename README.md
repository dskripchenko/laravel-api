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
        'user' => [
            'controller' => \App\Api\Versions\v1_0\Controllers\UserController::class,
            'actions' => [
               'register' => [
                   'exclude-all-middleware' => true,
               ],
               'login' => [],
               'logout' => false,
               'limitedAccess' => [
                   'middleware' => [
                       VerifyApiToken::class
                   ]
               ],
               'getSign' => [],
               'checkSign' => [
                   'middleware' => [
                       VerifyApiSign::class
                   ],
                   'exclude-middleware' => [],
               ],
            ],
            'exclude-all-middleware' => true,
            'middleware' => [],
            'exclude-middleware' => [],
        ]
      ],
      'middleware' => []
    ];
}

```

## AutoDoc comments to swagger
```php
/**
 * Method title
 * Method description
 *
 * @input type $variable1 name1
 * @input type $variable2 name2
 *
 * @output type $variable1 name1
 * @output type $variable2 name2
 */
```



## Example

* register `Dskripchenko\LaravelApiExample\ExampleApiServiceProvider`
* run `php artisan storage:link`
* open `/api/doc`