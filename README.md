## Installation

Run
```
php composer.phar require dskripchenko/laravel-api "^2.0.0"
```

or add

```
"dskripchenko/laravel-api": "^2.0.0"
```

to the ```require``` section of your `composer.json` file.


## Usage
See ```Dskripchenko\LaravelApiExample\```

* make `ApiModule` extended from `Dskripchenko\LaravelApi\Components\BaseModule`
* make `Api` extended from `Dskripchenko\LaravelApi\Components\BaseApi`
* define `getMethods` method
* override `getApiVersionList` in `ApiModule`, return array of `['version' => Api::class]`
* make `ApiServiceProvider` extended from `Dskripchenko\LaravelApi\Providers\ApiServiceProvider`
* override `getApiModule` in new `ApiServiceProvider`
* register `ApiServiceProvider`


## Api methods template
```php

use Dskripchenko\LaravelApi\Components\BaseApi;

class Api extends BaseApi 
{
    public static function getMethods(): array 
    {
        return [
          'controllers' => [
            '{controllersKey1}' => [
                'controller' => '{Controller::class}',
                'actions' => [
                    '{actionKey1}', // api/version/{controllersKey1}/{actionKey1}
                    '{actionKey2}' => '{real method name}', //api/version/{controllersKey1}/{actionKey2}
                    '{actionKey3}' => false, //disable //api/version/{controllersKey1}/{actionKey3}
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
 * @input [getSwaggerMetaInputs]
 *
 * @output type $variable1 name1
 * @output type $variable2 name2
 */

```
Available types ```string|number|integer|boolean|file```

## Components
* BaseApi
    * getMethods
    * getSwaggerTemplates
    * beforeCallAction
    * afterCallAction
    * getDefaultEmptyResponse
    * getMiddleware
    * registerApiComponent
* BaseModule
    * makeApi
    * getApiMiddleware
    * getApiPrefix
    * getAvailableApiMethods
    * getApiUriPattern
    * getApiVersionList
* Meta
    * action
        * create
        * read
        * update
        * delete
        * crud
    * column
        * string
        * boolean
        * number
        * integer
        * hidden
        * select
        * file
    * getSwaggerInputs
    * toArray

## Console
### Commands
* ApiInstall
    * getEnvConfig
    * fillEnvironment
    * reloadEnvironment
    * onBeginSetup
    * onEndSetup
* BaseCommand
    * askValid
    * validateInput

## Controllers
* ApiController
    * success
    * error
    * validationError
* ApiDocumentationController
    * index
* CrudController
    * meta
    * search
    * create
    * read
    * update
    * delete
    * getSwaggerMetaInputs
    
## Exceptions
* ApiErrorHandler
    * addErrorHandler
    * handle
* ApiException
    * getErrorKey
* Handler
    * render

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
    * getApiMiddleware
    * getApiVersionList
    * makeApi
* ApiErrorHandler
    * addErrorHandler
    * handle
    
## Middlewares
* ApiMiddleware
    * run
    
## Providers
* ApiServiceProvider
    * getApiModule
    * getApiErrorHandler
    * getApiRequest
    * makeApiRoutes
* BaseServiceProvider
    * mergeConfigFrom

## Requests
* BaseApiRequest
    * validateApiUriPattern
    * prepareApi
* CrudSearchRequest
    
## Resources
* BaseJsonResource
* BaseJsonResourceCollection

## Services 
* ApiResponseHelper
    * say
    * sayError
* CrudService
    * meta
    * query
    * resource
    * collection
    * search
    * create
    * read
    * update
    * delete
    
## Traits
* SwaggerApiTrait
    * getSwaggerApiConfig
    
## Interfaces
* CrudServiceInterface
    * meta
    * query
    * resource
    * collection
    * search
    * create
    * read
    * update
    * delete

## Example

* register `Dskripchenko\LaravelApiExample\ExampleApiServiceProvider`
* run `php artisan vendor:publish`
* run `php artisan storage:link`
* open web `/api/doc`
