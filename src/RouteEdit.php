<?php

namespace Lexroute;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Lexroute\Contracts\LexrouteException;
use Dpscan\Support\Facades\Dpscan;

class RouteEdit extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'route:edit {name?} {--a|api} {--f|fresh} {--c|cache} {--i|info}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Edit route with single command \n';

    protected $config,$template,$oldRoutesPath,$oldRoutes,$name,
    $exceptions,$controllerpath,$freshLaravelRoutes;

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
        if($this->option('api') === true){
             if(
                ($this->controllerpath
                    === $this->config->apicontrollerpath)
                     === false){
                $this->info('You cant use api update..');
                $this->info('set your api path first..');
                $this->info('to use -a');
                return exit();
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
        file_put_contents($this->oldRoutesPath,$stubs);
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
        $results[] = "url";
        $pattern = "@";
        if(str_contains($route,$pattern)){
            $results[] = "callback";
        }
        if(str_contains($route,"middleware")){
            $results[] = "middleware";
        }
        $pattern = [
            "get('",
            "post('",
            "put('",
            "patch('",
            "delete('",
            "option('",
            "any('"
        ];
        if(str_contains($route,$pattern)){
            $results[] = "method";
        }
        $pattern = [
            "match(",
        ];
        if(str_contains($route,$pattern)){
            $results[] = "methodmatch";
        }
        return $results;
    }

    protected function setRouteByOption($routes,$name,$options,$selected){
        if($selected !== "esc"){
            $this->info($selected);
            $function = 'updateBy'.ucfirst($selected);
            return $this->$function($routes,$name,$selected);
        }
        $this->error('exit');
        return exit(1);
    }

    protected function updateByUrl($routes = [], $name = null, $selected = null){
        if(count($routes) !== 1){
            $this->error('must 1 route to be update');
            return exit();
        }
        if(key($routes) !== $name){
            $this->error('route name not match');
            return exit();
        }
        $current = $this->ask('input current '.$selected);
        $current = '\''.$current.'\'';
        $params = $this->getRouteParams($routes);
        $uri = '\''.$params->uri().'\'';
        if($uri !== $current){
            $this->error($selected.' not match');
            return exit();
        }
        $new = $this->ask('input new '.$selected);
        $new = '\''.$new.'\'';
        $pattern = "/->name[(][\'].*[\'][)]?/";
        $route = (string) key($routes[$name]);
        $route =  preg_replace($pattern, null, $route);
        $namepattern = '->name(\''.$name.'\')';
        $route = str_replace($current,$new,$route);
        $route = $route.$namepattern;
        $routes[$name] = $route;
        return $routes;
    }

    protected function updateByCallback($routes = [], $name = null, $selected = null){
        if(count($routes) !== 1){
            $this->error('must 1 route to be update');
            return exit();
        }
        if(key($routes) !== $name){
            $this->error('route name not match');
            return exit();
        }
        $current = $this->ask('input current '.$selected);
        $methodaction = explode('@',$current);
        if(count($methodaction) !== 2){
            $this->error($selected.' Unknow method');
            return exit();
        }
        $uses = $current;
        $current = '\''.$current.'\'';
        $params = $this->getRouteParams($routes);
        $cb = explode('\\',$params->action['uses']);
        $callback = end($cb);
        $pattern = '\''.$callback.'\'';
        if($pattern !== $current){
            $this->error($selected.' not match');
            return exit();
        }
        $callback = $this->crToNamespace().DIRECTORY_SEPARATOR.$uses;
        list($controller,$action) = explode('@',$callback);
        if(method_exists($controller, $action) === false){
            $this->error($selected.' Unknow method');
            return exit();
        }
        $new = $this->ask('input new '.$selected);
        $callback = $this->crToNamespace().DIRECTORY_SEPARATOR.$new;
        list($controller,$action) = explode('@',$callback);
        if(method_exists($controller, $action) === false){
            $this->error( $callback .' Unknow method');
            return exit();
        }
        $route = (string) key($routes[$name]);
        $route = str_replace($uses, $new, $route);
        $routes[$name] = $route;
        $this->info($route);
        return $routes;
    }

    protected function crToNamespace(){
        $controller = $this->controllerpath;
        $controller = str_replace(['/','\\'],DIRECTORY_SEPARATOR,$controller);
        return ucfirst($controller);
    }

    protected function updateByMethod($routes = [], $name = null, $selected = null){
        if(count($routes) !== 1){
            $this->error('must 1 route to be update');
            return exit();
        }
        if(key($routes) !== $name){
            $this->error('route name not match');
            return exit();
        }
        $current = $this->ask('input current '.$selected);
        $params = $this->getRouteParams($routes);
        $methods = array_flip($params->methods);
        if(!isset($methods[strtoupper($current)])){
            $this->error($selected.' not match');
            return exit();
        }
        $new = $this->ask('input new '.$selected);
        $verbs = array_flip($this->methods);
        if(!isset($verbs[$new])){
            $this->error("unknow method");
            return exit();
        }
        $pattern = "/name[(][\'].*[\'][)]?/";
        $route = (string) key($routes[$name]);
        $route = preg_replace($pattern, null, $route);
        $method = $new."(";
        $route = str_replace($current."(", $method, $route);
        $namepattern = 'name(\''.$name.'\')';
        $route = $route.$namepattern;
        $routes[$name] = $route;
        return $routes;
    }

    protected function updateByMethodmatch($routes = [], $name = null, $selected = null){
        if(count($routes) !== 1){
            $this->error('must 1 route to be update');
            return exit();
        }
        if(key($routes) !== $name){
            $this->error('route name not match');
            return exit();
        }
        $oldroute = (string) key($routes[$name]);
        $pattern = "/Route::match[\(][\[].*[\]][,]/";
        preg_match($pattern,$oldroute,$match);
        $pattern = "/Route::match[\(][\[]/";
        $method = preg_replace($pattern,null,$match[0]);
        $method = str_replace(']',null,$method);
        $method = explode(',',$method);
        if(end($method)===""){
            array_pop($method);
        }
        $method = implode(',',$method);
        $this->info('old: '. $method );
        $new = $this->nestedAsk($this->methods);
        $new = "'".implode("','",$new)."'";
        $this->info('new: '. $new );
        $pattern = "/name[(][\'].*[\'][)]?/";
        $route = preg_replace($pattern, null, $oldroute);
        $route = str_replace($method, $new, $route);
        $namepattern = 'name(\''.$name.'\')';
        $route = $route.$namepattern;
        $routes[$name] = $route;
        return $routes;
    }

    protected function nestedAsk($key = [],$results=[]){
        $exit = 'enter to next';
        $key[]=$exit;
        $results = $results;
        if(count($key)!==0){
            $selected = $this->ask('input => '.implode(',',$key),end($key));
        }else{
            if(count($results) !== 0 ){
                return $results;
            }
            $this->error('Not have options to ask');
            return exit();
        }
        if($selected !== $exit){
            $results[]= $selected;
            $ask = array_flip($key);
            if(isset($ask[$selected])){
                unset($ask[$selected]);
                $ask = array_values(array_flip($ask));
                return $this->nestedAsk($ask,$results);
            }
            return $results;
        }
        return $results;
    }

    protected function getRouteParams($array = []){
        $array = $array[key($array)];
        $param = key($array);
        return $array[$param];
    }

    protected function updateByMiddleware($routes = [], $name = null, $selected = null){
        if(count($routes) !== 1){
            $this->error('must 1 route to be update');
            return exit();
        }
        if(key($routes) !== $name){
            $this->error('route name not match');
            return exit();
        }
        $route = (string) key($routes[$name]);
        $current = $this->ask('input current like auth,can,can '.$selected);
        $params = $this->getRouteParams($routes);
        $middleware = implode(',',$params->action['middleware']);
        if($this->option('api')=== true){
            if(!str_contains($current,'api')){
                $pattern = 'api,'.$current;
            }
        }else{
            if(!str_contains($current,'web')){
                $pattern = 'web,'.$current;
            }
        }
        if($pattern !== $middleware){
            $this->error($route.' not match');
            return exit();
        }
        $new = $this->ask('input new like auth:api,can or can,view '.$selected);
        $new = explode(',',$new);
        $new = "middleware('".implode("','",$new)."')";
        $current = explode(',',$current);
        $current = "middleware('".implode("','",$current)."')";
        $pattern = "/name[(][\'].*[\'][)]?/";
        $route = preg_replace($pattern, null, $route);
        $route = str_replace($current, $new, $route);
        $namepattern = 'name(\''.$name.'\')';
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
            if(!isset($laravelroutes[$name])){
                unset($routes[$i]);
                return $this->fixOldRoute(array_values($routes));
            }

            $action = $laravelroutes[$name]->action['uses'];
            if(is_object($action)){
                continue;
            }
            list($controller,$action) = explode('@',$action);
            if(method_exists($controller, $action) === true){
                if(str_contains($this->config->apicontrollerpath,$this->config->controllerpath)){
                    if($this->option('api') !== true){
                        $replace = str_replace([$this->config->controllerpath,'/'],[null,null],$this->config->apicontrollerpath);
                        if(str_contains($routes[$i],$replace.'\\')){
                            unset($routes[$i]);
                            return $this->fixOldRoute(array_values($routes));
                        }
                    }
                }
           }else{
                unset($routes[$i]);
                return $this->fixOldRoute(array_values($routes));
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
        if($this->option('api') === true){
            return $this->config->apicontrollerpath;
        }else{
            return $this->config->controllerpath;
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
