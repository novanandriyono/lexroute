<?php

namespace Lexroute;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Lexroute\Generator\Generator;
use Lexroute\Generator\ApiRouteGenerator;
use Dpscan;

class RouteUpdate extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'route:update {--a|api} {--f|fresh} {--c|cache} {--i|info}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update route with single command \n';

    protected $config,$template,$oldRoutesPath,$oldRoutes,$name,
    $exceptions,$freshRoute,
    $middleware,$freshLaravelRoutes;

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
        return $this->updateRoute();
    }

    protected function updateRoute(){
        $this->mergeRoute();
        if($this->option('cache') === true){
            $this->call('route:cache');
        }
    }

    protected function mergeRoute(){
        $this->controllerpath = $this->getControllerPath();
        $this->template = $this->template();
        $this->middleware = $this->getMiddleware();
        $this->oldRoutesPath = $this->getOldRoutePath();
        $this->oldRoutes = $this->getOldRoute();
        $this->freshRoute = $this->getFreshRoute();
        $this->freshLaravelRoutes = $this->getFreshLaravelRoute();
        if($this->option('fresh') === true){
            $items = $this->setFrontpage($this->freshRoute);
        }else{
            $items = $this->setFrontpage($this->getNewRoutes());
        }
        $stubs =  $this->template()."\n"."\n".implode(";\n",$items).";\n";
        $stubs = preg_replace('/;+/', ';', $stubs);
        file_put_contents($this->oldRoutesPath,$stubs);
        return exit('done');
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
        $frontpage = [];
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
            if(is_array($this->config->frontpage) === true){
                if(count($this->config->frontpage) === 1){
                    $frontpagename = key($this->config->frontpage);
                    $glue = $this->config->frontpage[$frontpagename];
                    if($frontpagename === $key){
                        $frontpage[$key] = $item;
                        unset($results[$key]);
                    }
                }
            }
        }

        if(is_array($this->config->frontpage) === true){
            if(count($this->config->frontpage) === 1){
                 $results = $frontpage + $results;
            }
        }

        if((count($auth)===1)){
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
        if(is_file(base_path($path)) !== true){
            $this->info('route path not found');
            return exit();
        }
        return base_path($path);
    }


    protected function filterApi(){
        $folder = Dpscan::setdir(base_path($this->controllerpath))->onlyfiles();
        if($this->option('api') !== true){
            if(str_contains($this->config->apicontrollerpath,$this->controllerpath) === true){
              $folder = $folder->notcontains([$this->config->apicontrollerpath]);
            }
        }else{
            if(str_contains($this->config->apicontrollerpath,$this->controllerpath) === true){
              $folder = $folder->contains([$this->config->apicontrollerpath]);
            }
        }
        return array_values($folder->items()->toArray());
    }

    protected function fixOldRoute($routes = []){
        $items = [];
        $laravelroutes = $this->getFreshLaravelRoute();
        $authroutes = [];
        for ($i=0; $i < count($routes); $i++){
            if($routes[$i] === 'Auth::routes()'){
                continue;
            }
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
            if(method_exists($controller, $action) === false){
                unset($routes[$i]);
                return $this->fixOldRoute(array_values($routes));
            }
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
                    if (strpos($v, '__') === false) {
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
        $generator = new Generator(
            $this->getActionList(),
            $this->controllerpath,
            $this->middleware,
            $this->config->translations);
        if($this->option('api')===true){
            $routes = $generator->api();
        }else{
            $routes = $generator->web();
        }
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
                return $item[] = $lists;
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
