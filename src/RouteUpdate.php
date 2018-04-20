<?php

namespace Lexroute;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Lexroute\Generator\Generator;
use Lexroute\Generator\ApiRouteGenerator;
use Lexroute\Contracts\LexrouteException;
use Dpscan\Support\Facades\Dpscan;
class RouteUpdate extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'route:update {name?} {--a|api} {--f|fresh} {--c|cache} {--i|info}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update route with single command \n';

    protected $config,$template,$oldRoutesPath,$oldRoutes,$name,
    $inputtype,$fresh,$exceptions,$controllerpath,$freshRoute,
    $middleware,$freshLaravelRoutes;

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
                $this->info('You cant use api update.. ');
                $this->info('set your api path first..');
                $this->info('to use -a');
                return exit();
             }
        }
        $this->name = $this->argument('name');
        $this->template = $this->template();
        $this->middleware = $this->getMiddleware();
        $this->oldRoutesPath = $this->getOldRoutePath();
        $this->oldRoutes = $this->getOldRoute();
        $this->freshRoute = $this->getFreshRoute();
        $this->freshLaravelRoutes = $this->getFreshLaravelRoute();
        return $this->updateRoute();
    }

    protected function updateRoute(){
        if($this->name !== null){
            $this->setUpdate();
        }else{
            $this->mergeRoute();
        }

        if($this->option('cache') === true){
            $this->call('route:cache');
        }
    }

    protected function setUpdate(){
        if($this->name !== null){
            $routers = $this->setFrontpage(
                $this->updateRouteByName($this->name)
            );
            $stubs = $this->template."\n"."\n".implode(";\n",$routers).";\n";
            file_put_contents($this->oldRoutesPath,$stubs);
        }
    }

    protected function updateRouteByName($name=null){
        $oldroutes = $this->oldRoutes;
        $route = $this->getRouteByName($oldroutes,$name);
        if(count($route) === 1){
            $options = $this->getRouteOption($route[$name]);
            $selected = $this->choice('choose available ', $options);
            $newroutes = $this->setRouteByOption($route,$name,$options,$selected);
            return $this->doMerge(array_flip($oldroutes), array_flip($newroutes));
        }else{
            $this->error('Route Not Found...');
        }
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
        }else{
            $this->error('exit');
        }
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

    protected function mergeRoute(){
        if($this->option('fresh') === true){
            $items = $this->setFrontpage($this->freshRoute);
        }else{
            $items = $this->setFrontpage($this->getNewRoutes());
        }
            $stubs =  $this->template()."\n"."\n".implode(";\n",$items).";\n";
        $stubs = preg_replace('/;+/', ';', $stubs);
        file_put_contents($this->oldRoutesPath,$stubs);
    }

    protected function getNewRoutes(){
        $newroutes = array_flip(array_values($this->freshRoute));
        $oldroutes = array_flip($this->oldRoutes);
        return $this->doMerge($newroutes, $oldroutes);
    }

    protected function doMerge($new = [], $old = []){
        $allroutes = array_keys($new + $old);
        $results=[];
        $auth = [];
        if($this->option('info') === true){
        $hasupdate=[];
        $notupdate=[];
        }
        for ($i=0; $i < count($allroutes) ; $i++) {
            $item = $allroutes[$i];
            $pattern = "/Route[:][:].*[name][(][']?/";
            $key =  preg_replace($pattern, null, $item);
            $key = str_replace("')",null,$key);
            if($this->option('info') === true){
                if(!isset($results[$key])){
                    $hasupdate[] = "";
                }else{
                    $notupdate[] = $key;
                }
            }
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

        if($this->option('info') === true){
            $this->info('updated route: '. count($hasupdate));
            $this->info("not updated : ". count($notupdate). "\n[".implode(", ",$notupdate)."]");
        }
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


    protected function filterApi(){
        $folder = Dpscan::setdir(base_path($this->controllerpath))
        ->onlyfiles()->items()->toArray();
        for ($i=0; $i < count($folder); $i++) {
            if(str_contains($this->config->apicontrollerpath,
                $this->config->controllerpath)){
                if($this->option('api') !== true){
                    $replace = str_replace(
                        [$this->config->controllerpath,'/'],
                        [null,null],
                        $this->config->apicontrollerpath);
                    if(str_contains($folder[$i],$replace)){
                        unset($folder[$i]);
                    }
                }
            }
        }
        return array_values($folder);
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

    protected function getActionList(){
        $files = $this->filterApi();
        $result = [];
        for ($i=0; $i < count($files); $i++) {
            $file = $files[$i];
            $classFile = str_replace([base_path($this->controllerpath).'\\','.php'],null,$file);
            $re = '/public function.*?\(/';
            $str = file_get_contents($file);
            preg_match_all($re, $str, $matches, PREG_SET_ORDER, 0);
            foreach ($matches as $key => $value) {
                foreach ($value as $k => $v) {
                    if (strpos($v, '__construct') === false) {
                        $val = str_replace(['public function ','('],[$classFile.'@',null],$v);
                        if(strpos($val, 'public function.*?') === false){
                            if(strpos($val, ',') === false){
                            $result[] = $val;
                            }
                        }
                    }
                }
            }
        }
        return $result;
    }

    protected function getFreshRoute(){
        if($this->option('api')===true){
            $generator = new ApiRouteGenerator($this->getActionList(),$this->controllerpath,$this->middleware,$this->config->translations);
        }else{
            $generator = new Generator($this->getActionList(),$this->controllerpath,$this->middleware,$this->config->translations);
        }
        $routes = $generator->get();
        $glue = str_replace('\\','.',str_replace('/','.',$this->controllerpath));
        $results = [];
        for ($i=0; $i < count(array_keys($routes)) ; $i++) {
            $route = str_replace('\\','.',str_replace('/','.',$routes[array_keys($routes)[$i]]));
            if(!str_contains($route,$glue)){
                $results[array_keys($routes)[$i]] = $routes[array_keys($routes)[$i]];
            }
        }
        return $results;
    }

    protected function setFrontpage(array $lists = []){
        if($this->option('api') === true){
            $this->info('Frontpage just for web routes');
            return $lists;
        }
        if(is_array($this->config->frontpage) === true){
            if(count($this->config->frontpage) === 1){
                $frontpagename = key($this->config->frontpage);
                $glue = $this->config->frontpage[$frontpagename];
                if(str_contains($glue,$frontpagename) === true){
                   $this->info('Frontpage name must be different from base name 1');
                   return exit();
                }
                if(str_contains($frontpagename,$glue) === true){
                   $this->info('Frontpage name must be different from base name 2');
                   return exit();
                }
                $freshLaravelRoutes = $this->getFreshLaravelRoute();
                if(isset($freshLaravelRoutes[$frontpagename])){
                   $this->info('Frontpage has been set nothing to set pass');
                   return $lists;
                }
                if(!isset($this->freshLaravelRoutes[$glue])){
                   $this->info($glue. ' Unknow base router name 3');
                   return exit();
                }
                $route = $freshLaravelRoutes[$glue];
                $url = str_shuffle($frontpagename);
                $key = ucfirst($url);
                $keyArray = $url.'..index';
                $action = $key.'/Controller@index';
                $generator = new Generator([$action],$this->controllerpath,$this->middleware,$this->config->translations);
                $list = $generator->get()[$keyArray];
                $newaction = str_replace($route->action['namespace'].'\\',null,$route->action['uses']);
                $item = str_replace([$url.'/',$action,$keyArray], ["/",$newaction,$frontpagename], $list);
                if($lists[0] === 'Auth::routes()'){
                    $authroutes[$lists[0]] = $lists[0];
                    $lists[0] = $item;
                    $item = $authroutes;
                }
                return $item + $lists;
            }
        }
        return $lists;
    }

    protected function fixFolder($ff = []){
        unset($ff[0]);
        unset($ff[1]);
        return array_values($ff);
    }

    protected function getControllerPath(){
        if($this->option('api') === true){
            $path = $this->config->apicontrollerpath;
        }else{
            $path = $this->config->controllerpath;
        }
        return $path;
    }

    protected function getMiddleware(){
        if($this->option('api') === true){
            return $this->config->apimiddleware;
        }else{
            return $this->config->middleware;
        }
    }

    protected function frontpage(){
        return $this->config->frontpage;
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
