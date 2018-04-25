<?php
namespace Lexroute\Generator;
use Lexroute\Contracts\Generator\Generator as GeneratorInterface;
class ApiRouteGenerator extends Generator{

	protected $lists,$controllerpath,$middleware;

	public function api(){
        return $this->generator();
    }

    protected function generator(){
        $lists = $this->lists;
        $controllerpath = $this->controllerpath;
        $routers = [];
        for ($i=0; $i < count($lists); $i++){
            $action = $lists[$i];
            $act = $this->actionForRoute($action);
            $name = $this->nameForRoute($action);
            $url = $this->uriForRoute($action);
            $method = $this->methodForRoute($name);
            $routers[$name] = "Route::";
            if(count($this->middleware) !== 0){
                $routers[$name] .= "middleware('".implode("','",$this->middleware)."')->";
            }
            if(is_array($method)){
                $routers[$name] .= "match(['".$method[0]."','".$method[1]."'],'";
            }else{
                $routers[$name] .= $method."('";
            }
            $routers[$name] .= $url;
            $routers[$name] .= "','";
            $routers[$name] .= $act;
            $routers[$name] .= "')";
            $routers[$name] .= "->name('".$name."')";

        }
        return $routers;
    }


    protected function actionForRoute($action){
        $pattern = $this->newdash($this->removeLastPath($this->controllerpath));
        $action = $this->newdash($action);
        $action = str_replace([$pattern,'/'],['null',DIRECTORY_SEPARATOR],$action);
        return $action;
    }

    protected function uriForRoute($action){
        $action = parent::uriForRoute($action);
        $part = explode('/',$action);
        if(str_contains($action,$part[0])===true){
            $action = str_replace($part[0],null,$action);
        }
        return $action;
    }

    protected function nameForRoute($action){
        $lastpath = strtolower($this->getlastPath($this->controllerpath));
        $name = strtolower($this->getlastPath($this->controllerpath));
        $name = str_replace($lastpath,null,$name);
        return parent::nameForRoute($action);
    }
}