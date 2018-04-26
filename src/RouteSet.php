<?php

namespace Lexroute;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Lexroute\Contracts\LexrouteException;
use Dpscan;

class RouteSet extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'route:set {name?} {--a|api} {--c|cache} {--i|info}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'add params to route with single command';

    protected $config,$template,$oldRoutesPath,$oldRoutes,$name,
    $exceptions,$freshLaravelRoutes;

    protected $controllerpath = 'App\Http\Controllers';

    protected $type = ['web','api'];

    protected $methods = ['get','post','put','patch','delete','any','option'];

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(LexrouteException $exceptions)
    {
        $exceptions->checkError();
        $this->exceptions = $exceptions;
        $this->config();
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {

        $this->controllerpath =  $this->getControllerPath();
        if(is_array($this->config->frontpage) === true){
            if(count($this->config->frontpage) === 1){
                $frontpagename = key($this->config->frontpage);
                if($this->argument('name') === $frontpagename){
                $this->info('Cant edit frontpage');
                return exit();
                }
            }
        }
        $this->name = $this->argument('name');
        $this->template = $this->template();
        $this->oldRoutesPath = $this->getOldRoutePath();
        $this->oldRoutes = $this->getOldRoute();
        $this->freshLaravelRoutes = $this->getFreshLaravelRoute();
        return $this->updateRoute();
    }

    protected function updateRoute(){
        if($this->name === null){
            $this->name = $this->ask('please input name route');
        }
        $routers = $this->updateRouteByName();
        $stubs = $this->template."\n"."\n".implode(";\n",$routers).";\n";
        if(file_put_contents($this->oldRoutesPath,$stubs)){
            $this->call('route:clean');
        }
        if($this->option('cache') === true){
            $this->call('route:cache');
        }
    }

    protected function updateRouteByName(){
        $oldroutes = $this->oldRoutes;
        $route = $this->getRouteByName($oldroutes,$this->name);
        if(count($route) === 1){
            $options = $this->getRouteOption($route[$this->name]);
            $selected = $this->choice('choose available ', $options);
            $newroutes = $this->setRouteByOption($route,$this->name,$options,$selected);
            return $this->doMerge(array_flip($oldroutes), array_flip($newroutes));
        }
        return exit;
    }

    protected function getRouteByName($array = [], $name = null){
        $results = [];
        for ($i=0; $i < count($array) ; $i++) {
            $item = $array[$i];
            $pattern = "->name('".$name."')";
            if((str_contains($item,$pattern) === true)
                && (isset($this->freshLaravelRoutes[$name]) === true))
            {
                $line = $item;
                $route = $this->freshLaravelRoutes[$name];
                $results[$name] = [$line => $route];
            }
        }
        return $results;
    }

    protected function getRouteOption($route = null){
        $route = key($route);
        if(str_contains($route,"middleware") === false){
            $results[] = "middleware";
        }
        if(isset($results) === false){
            $this->info('noting to set');
            return exit();
        }
        return $results;
    }

    protected function setRouteByOption($routes,$name,$options,$selected){
        if($selected !== "esc"){
            $this->info($selected);
            $function = 'setNewBy'.ucfirst($selected);
            return $this->$function($routes,$name,$selected);
        }
        $this->error('exit');
        return exit(1);
    }

    protected function getRouteParams($array = []){
        $array = $array[key($array)];
        $param = key($array);
        return $array[$param];
    }

    protected function setNewByMiddleware(array $routes,string $name,string $selected){
        if(count($routes) !== 1){
            $this->error('must 1 route to be update');
            return exit();
        }
        if(key($routes) !== $name){
            $this->error('route name not match');
            return exit();
        }
        if(Route::has($name)=== false){
            $this->error('route name not match');
            return exit();
        }
        $route = (string) key($routes[$name]);
        $new = $this->ask('input new middleware like auth,can,can');
        if(str_contains($new,',') === true){
            $new = explode(',',$new);
        }else{
            $new = [$new];
        }
        $new = "middleware('".implode("','",$new)."')->";
        $pattern = "/name[(][\'].*[\'][)]?/";
        $route = preg_replace($pattern, null, $route);
        if($this->option('api') === true){
        $route = str_replace('Route::','Route::'.$new);
        $namepattern = 'name(\''.$name.'\')';
        }else{
        $namepattern = $new.'name(\''.$name.'\')';
        }
        $route = $route.$namepattern;
        $routes[$name] = $route;
        return $routes;
    }

    protected function doMerge($new = [], $old = []){
        $allroutes = array_keys($new + $old);
        $results=[];
        $auth = [];
        for ($i=0; $i < count($allroutes) ; $i++) {
            $item = $allroutes[$i];
            $pattern = "/Route[:][:].*[name][(][']?/";
            $key =  preg_replace($pattern, null, $item);
            $key = str_replace("')",null,$key);
            $results[$key] = $item;
            if($item === "Auth::routes()"){
                $auth[$key] = $item;
                unset($results[$key]);
            }
        }

        if(count($auth)===1){
            $results = $auth + $results;
        }
        $results = array_values($results);
        return $results;
    }

    protected function getOldRoute(){
        $key = '==\(^.^ )/==';
        $routes = file_get_contents($this->oldRoutesPath);
        $template = str_replace(["\r\n","\r","\n",$key],null,$this->template);
        $routes = str_replace(["\r\n","\r","\n",$template,$key],null,$routes);
        $routes = preg_replace(["/\s{2,}/","/[\s\t]/","/[\;][\r\n|\r|\n]|[\;]$/"], [' ',' ',null], $routes);
        $routes = str_replace([';Route::',';Auth::'],[$key.';Route::',$key.';Auth::'],$routes);
        $routes = explode($key.';',$routes);
        if($routes[0] === ""){
            unset($routes[0]);
            $routes = array_values($routes);
        }
       return $this->fixOldRoute($routes);
    }

    protected function getOldRoutePath(){
        $path = $this->config->routepath;
        $path .= DIRECTORY_SEPARATOR;
        if($this->option('api')===true){
        $path .= $this->config->apiroute.'.php';
        }else{
        $path .= $this->config->webroute.'.php';
        }
        return base_path($path);
    }


    protected function fixOldRoute($routes = []){
        $items = [];
        $laravelroutes = $this->getFreshLaravelRoute();
        $authroutes = [];
        for ($i=0; $i < count($routes); $i++){
            if($routes[$i] === 'Auth::routes()'){
                continue;
            }
            $pattern = "/^.*->name[\(][\']/";
            $pattern2 = "/[\'][\)]/";
            $name = preg_replace([$pattern,$pattern2],null,$routes[$i]);
            if(Route::has($name) === false){
                unset($routes[$i]);
                return $this->fixOldRoute(array_values($routes));
            }

            $action = $laravelroutes[$name]->action['uses'];
            if(is_object($action)){
                continue;
            }
            list($controller,$action) = explode('@',$action);
            if(method_exists($controller, $action) === false){
                unset($routes[$i]);
                return $this->fixOldRoute(array_values($routes));
            }
            if($this->config->apicontrollerpath !== $this->controllerpath){
                if(str_contains($this->config->apicontrollerpath,$this->controllerpath)){
                    if($this->option('api') !== true){
                        $replace = str_replace([$this->controllerpath,'/'],[null,null],$this->config->apicontrollerpath);
                        if(str_contains($routes[$i],$replace.'\\')){
                            unset($routes[$i]);
                            return $this->fixOldRoute(array_values($routes));
                        }
                    }
                }
            }
        }
        return array_values($routes);
    }

    protected function getFreshLaravelRoute(){
        $collection = Route::getRoutes();
        $collection->refreshNameLookups();
        return (array) $collection->getRoutesByName();
    }

     protected function getControllerPath(){
        if($this->config->controllerpath !== false){
          $this->controllerpath = $this->config->controllerpath;
        }
        if($this->option('api') === true){
            if(str_contains($this->config->apicontrollerpath,$this->controllerpath) === true){
                return $this->controllerpath;
            }
            if($this->config->apicontrollerpath !== false){
                return $this->controllerpath;
            }else{
                $this->info('You cant use api update.. ');
                $this->info('set your api path first..');
                $this->info('to use -a');
                return exit();
            }
        }else{
            return $this->controllerpath;
        }
    }

    protected function getMiddleware(){
        if($this->option('api') === true){
            return $this->config->apimiddleware;
        }else{
            return $this->config->middleware;
        }
    }

    protected function template(){
        if($this->option('api') === true){
            return file_get_contents(__DIR__ . '/stubs/api.stub');
        }else{
            return file_get_contents(__DIR__ . '/stubs/web.stub');
        }
    }

    private function config(){
           return $this->config = (object) config('lexroute');
    }
}
