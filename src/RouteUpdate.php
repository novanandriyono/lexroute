<?php

namespace Lexroute;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Lexroute\Generator\Generator;
use Lexroute\Generator\ApiRouteGenerator;
use Lexroute\Contracts\LexrouteException;
class RouteUpdate extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'route:update {name?} {--a|api} {--w|web} {--f|fresh} {--c|cache} {--i|info}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update route with single command \n';

    protected $config,$template,$oldRoutesPath,$oldRoutes,$name,$inputtype,$fresh,$exceptions,$controllerpath,$freshRoute,$middleware;

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
        $this->inputtype = ($this->option('api')===true)?'api':'web';
        $this->name = $this->argument('name');
        $this->fresh = $this->option('fresh');
        $this->template = $this->template();
        $this->middleware = $this->getMiddleware();
        $this->controllerpath =  $this->getControllerPath();
        $this->oldRoutesPath = $this->getOldRoutePath();
        $this->freshRoute = $this->getFreshRoute();
        $this->oldRoutes = $this->getOldRoute();
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
            $routers = $this->updateRouteByName($this->name);
            $stubs = $this->template."\n"."\n".implode(";\n",$routers).";\n";
            file_put_contents($this->oldRoutesPath,$stubs);
        }
    }

    protected function updateRouteByName($name=null){
        $oldroutes = $this->oldRoutes;
        $route = $this->getRouteByName($oldroutes,$name);
        if($route !== null){
            $options = $this->getRouteOption($route[$name]);
            $selected = $this->choice('choose available ', $options);
            $newroutes = $this->setRouteByOption($route,$name,$options,$selected);
            return $this->doMerge( array_flip($oldroutes), array_flip($newroutes));
        }else{
            $this->error('Route Not Found...');
        }
    }

    protected function getRouteByName($array = [], $name = null){
        $results = [];
        for ($i=0; $i < count($array) ; $i++) { 
            $item = $array[$i];
            $pattern = "->name('".$name."')";
            if(str_contains($item,$pattern)){
                $results[$name] = $array[$i];
            }
        }
        return $results;
    }

    protected function getRouteOption($route = null){
        $results[] = "url";
        $pattern = "->name('";
        if(str_contains($route,$pattern)){
            $results[] = "name";
        }
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

    protected function updateByName($routes = [], $name = null, $selected = null){
        if(count($routes) !== 1){
            $this->error('must 1 route to be update');
            return exit();
        }
        if(key($routes) !== $name){
            $this->error('route name not match');
            return exit();
        }
        $this->info('');
        return $routes;
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
        if(!str_contains($routes[$name],$current)){
            $this->error($selected.' not match');
            return exit();
        }
        $new = $this->ask('input new '.$selected);
        $new = '\''.$new.'\'';
        $pattern = "/->name[(][\'].*[\'][)]?/";
        $route =  preg_replace($pattern, null, $routes[$name]);
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
        $current = '\''.$current.'\'';
        if(!str_contains($routes[$name],$current)){
            $this->error($selected.' not match');
            return exit();
        }
        $new = $this->ask('input new '.$selected);
        $pattern = "/name[(][\'].*[\'][)]?/";
        $route = preg_replace($pattern, null, $routes[$name]);
        $pattern = "/[,].['].*[@].*['][)][-][>]?/";
        $callback = ",'".$new."')->";
        $route = preg_replace($pattern, $callback, $route);
        $namepattern = 'name(\''.$name.'\')';
        $route = $route.$namepattern;
        $routes[$name] =  $route;
        return $routes;
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
        $current = $current.'(';
        if(!str_contains($routes[$name],$current)){
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
        $route = preg_replace($pattern, null, $routes[$name]);
        $method = $new."(";
        $route = str_replace($current, $method, $route);
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
        // $current = $this->ask('input one of method of '.$selected);

        $oldroute = $routes[$name];
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
        $route = preg_replace($pattern, null, $routes[$name]);
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

    protected function updateByMiddleware($routes = [], $name = null, $selected = null){
        if(count($routes) !== 1){
            $this->error('must 1 route to be update');
            return exit();
        }
        if(key($routes) !== $name){
            $this->error('route name not match');
            return exit();
        }
        $current = $this->ask('input current like [\'auth\',\'can\'] '.$selected);
        if(!str_contains($routes[$name],$current)){
            $this->error($selected.' not match');
            return exit();
        }
        $new = $this->ask('input new like [\'new\'] or [\'new\',\'new2\'] '.$selected);
        $pattern = "/[[]['].*['][]]/";
        if(preg_match($pattern,$new) === 0){
            $this->error('unknow middleware format');
            return exit();
        }
        $pattern = "/name[(][\'].*[\'][)]?/";
        $route = preg_replace($pattern, null, $routes[$name]);
        $route = str_replace($current, $new, $route);
        $namepattern = 'name(\''.$name.'\')';
        $route = $route.$namepattern;
        $routes[$name] = $route;
        return $routes;
    }

    protected function mergeRoute(){
        if($this->option('fresh') === true){
            $stubs =  $this->template()."\n"."\n".implode(";\n",$this->freshRoute).";\n";
        }else{
            $stubs =  $this->template()."\n"."\n".implode(";\n",$this->getNewRoutes()).";\n";
        }
        $stubs = preg_replace('/;+/', ';', $stubs);
        file_put_contents($this->oldRoutesPath,$stubs);
    }

    protected function getNewRoutes(){
        $newroutes = array_flip(array_values($this->freshRoute));
        $oldroutes = array_flip($this->oldRoutes);
        $this->info('last route: '. count($oldroutes));
        $this->info('new route: '. count($newroutes));
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
        $routes = file_get_contents($this->oldRoutesPath);
        $template = str_replace(["\r\n","\r","\n"],null,$this->template);
        $routes = str_replace(["\r\n","\r","\n",$template],null,$routes);
        $routes = preg_replace(["/\s{2,}/","/[\s\t]/"], ' ', $routes);
        $key = '==\(^.^ )/==';
        $routes = str_replace([';Route::',';Auth::'],[$key.';Route::',$key.';Auth::'],$routes);
        $routes = explode($key.';',$routes);
        if($routes[0] === ""){
            unset($routes[0]);
            $routes = array_values($routes);
        }
        $last = end($routes);
        $pattern = "/->name[\(][\'].*[\'][\)][\;]/";
        if(preg_match($pattern, $last,$match) === 1){
            $item = $match[0];
            $pattern = str_replace(';',null,$match[0]);
            $last = str_replace($item,$pattern,$last);
            array_pop($routes);
            $routes[] = $last;
        }
        return $this->fixOldRoute($routes);
    }

    protected function getOldRoutePath(){
        if($this->option('api')===true){
        $path = $this->config->routepath.DIRECTORY_SEPARATOR.$this->config->apiroute.'.php';
        }else{
        $path = $this->config->routepath.DIRECTORY_SEPARATOR.$this->config->webroute.'.php';
        }
        return base_path($path);
    }

    protected function getControllerList($folder=null,$next=null,$il=null){
        if($folder === null){
            $folder = $this->filterApi();
        }
        $this->files = [];
        for ($i=0; $i < count($folder); $i++) {
            if($next === null){
                $isfolder = $this->controllerpath.DIRECTORY_SEPARATOR.$folder[$i];
            }else{
                $isfolder = $this->controllerpath.DIRECTORY_SEPARATOR.$next.DIRECTORY_SEPARATOR.$folder[$i];
            }

            if(is_file($isfolder)){
                    $this->files[] = $isfolder;
            }elseif(is_dir($isfolder)){
                $newscan =  $this->fixFolder(scandir($isfolder));
                $this->getControllerList($newscan,$folder[$i],$i);
            }else{

            }
        }
        return $this->files;
    }

    protected function filterApi(){
        $folder = $this->getFolder();
        for ($i=0; $i < count($folder); $i++) {
            if(str_contains($this->config->apicontrollerpath,$this->config->controllerpath)){
                if($this->option('api') !== true){
                    $replace = str_replace([$this->config->controllerpath,'/'],[null,null],$this->config->apicontrollerpath);
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
        for ($i=0; $i < count($routes); $i++){
            $pattern = "/^.*->name[\(][\']/";
            $pattern2 = "/[\'][\)].*/";
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
        return $collection->getRoutesByName();
    }

    protected function getActionList(){
        $files = $this->getControllerList();
        $result = [];
        for ($i=0; $i < count($files); $i++) {
            if($this->option('api') === false){

            }
            $file = $files[$i];
            $classFile = str_replace(['.php'],[null],$file);
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
        $glue = str_replace('\\','.',str_replace('/','.',$this->controllerpath));
        if($this->option('api')===true){
            $generator = new ApiRouteGenerator($this->getActionList(),$this->controllerpath,$this->middleware,$this->config->translations);
        }else{
            $generator = new Generator($this->getActionList(),$this->controllerpath,$this->middleware,$this->config->translations);
        }
        $routes = $generator->get();
        $results = [];
        for ($i=0; $i < count(array_keys($routes)) ; $i++) {
            $route = str_replace('\\','.',str_replace('/','.',$routes[array_keys($routes)[$i]]));
            if(!str_contains($route,$glue)){
                $results[] = $routes[array_keys($routes)[$i]];
            }
        }
        return $results;
    }

    protected function setFrontpage($lists){
        $item = [];
        $frontpagename = array_keys($this->config->frontpage);
        $glue = $this->config->frontpage[end($frontpagename)];
        $paternUrl = "/'\/.*?',?/";
        if(isset($lists[$glue])){
            $list = $lists[$glue];
            $list = preg_replace($paternUrl, "'/',", $list);
            $item[end($frontpagename)] = str_replace($glue , end($frontpagename),  $list);
            return $item + $lists;
        }else{
            $this->info('frontpage name not found');
            return  $lists;
        }
    }

    protected function fixFolder($ff = []){
        unset($ff[0]);
        unset($ff[1]);
        return array_values($ff);
    }

    protected function getFolder($apppath = null){
        if($apppath === null){
            $apppath = $this->controllerpath;
        }
        if(file_exists($apppath)){
            $apppath = $this->fixFolder(scandir($apppath));
        }else{
            $this->error('folder not found :'.$apppath);
            $apppath = [];
        }
        return $apppath;
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
