# lexroute
Extra command route for laravel

## Install via Composer

```
composer require novanandriyono/lexroute:dev-master
```

### Update Route Web

Update all list web routes 

```
php artisan route:update
```

Update one of routes

```
php artisan route:update routename
```

### Update Route Api

```
php artisan route:update -a
```

Will be update your route api file


### Publish Config

```
php artisan vendor:publish --tag=lexroute.config
```

Will be update your route api file

### Notice
Route must have tag ->name if not will be erased or lost.
Still dev for update name option and some check

## License

This project is licensed under the MIT License - see the [LICENSE.md](LICENSE.md) file for details