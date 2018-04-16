# lexroute
Extra command route for laravel

## Install via Composer

```
composer require novanandriyono/lexroute:dev-master
```

![alt tag](https://preview.ibb.co/bAUomn/ezgif_2_bd90ed7a20b.gif "---")
### Update Route Web

Update all list web routes 

```
php artisan route:update
```

Update one of routes

```
php artisan route:update routename
```

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