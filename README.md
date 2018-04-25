# lexroute
Extra command route for laravel

## Install via Composer
```
composer require novanandriyono/lexroute
```
### How to use
![alt tag](https://image.ibb.co/kBS2wx/lexroute.gif "Lexroute")


## Update Route
Controller that has public function. [recursive]
```
php artisan route:update
```

###Edit one of routes
```
php artisan route:edit route.name
```
it will show to use same options if route name has found.

### Publish Config
By default it will lock default location laravel app. please take a look config.
if we have different value, we can publish config and edit it;
```
php artisan vendor:publish --tag=lexroute.config
```

### About Updating Api Route
Using -a for update or edit api route
```
php artisan route:update -a
```
or
```
php artisan route:edit route.name -a
```
we must publish config to use api update. api Controller must in one folder like
```
'apicontrollerpath' => 'App/Http/Controllers/Api',
```

### User Notice
* Route must have tag ->name if not will be erased or lost.
* Route name will follow with function name.
* Creating new function it mean will creating route line when update.
* Creating new name or edit name will be save and it will creating new one
* Using tag name must at last line.
* Not exists callback will be remove except Object Closure.
* Still dev for update name option and some check.
* Just can be use on local env and artisan console.
* And Maybe we must removing this package on production, So someone does not use this module and edit our route XD

### Function Name is Route Name
if we creating new function like
```
class Items extends Controller{
publich function likethis(){};
```
it will creating route like
```
->name('items.likethis');
```

### Available auto method route where update:
* function name with contains 'index,show,show,create,get' at first line will be result **Route:get**
* function name with contains 'store' at first line will be result **Route:post**
* function name with contains 'update' at first line will be result **Route:match**
* function name with contains 'patch' at first line will be result **Route:patch**
* function name with contains 'PUT' at first line will be result **Route:put**
* function name with contains 'delete,destroy' at first line will be result **Route:delete**
* except from the list above wil be result **Route:get**

### Not Support
* This command not support 'use' on head it will be removed on update like:

```
<?php

use SomeClass/Foo;
```
But we can use like this for callback like:
```
Route::get('foo',function(SomeClass/Foo $foo){
return $foo;
})->name('someclass.foo');
```
if you can.. hee;
* add route match when edit method.
* etc please contact me. emang siapa yang mau make:p

## Why
I made lexroute because T_T I am still learning and very lazy to edit
and add Route line. Make it fast at first laravel application. (T_T).
[@novanandriyono](https://twitter.com/kiyoshihikaru)


## License
This project is licensed under the MIT License - see the [LICENSE.md](LICENSE.md) file for details