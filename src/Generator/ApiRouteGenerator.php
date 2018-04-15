<?php
namespace Lexroute\Generator;
use Lexroute\Contracts\Generator\Generator as GeneratorInterface;
class ApiRouteGenerator extends Generator implements GeneratorInterface{

	protected $lists,$controllerpath,$middleware;

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
                $routers[$name] .= "middleware(".json_encode($this->middleware).")->";
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
        $action = str_replace($pattern,null,$action);
        $action = explode('/',$action);
        array_shift($action);
        return implode(DIRECTORY_SEPARATOR,$action);
    }

    protected function uriForRoute($action){
        $action = strtolower($action);
        $lastpath = strtolower($this->getlastPath($this->controllerpath));
        $action = str_replace(strtolower($this->controllerpath),null,$action);
        $action = str_replace($lastpath,null,$action);
        return parent::uriForRoute($action);
    }

    protected function nameForRoute($action){
        $lastpath = strtolower($this->getlastPath($this->controllerpath));
        $name = strtolower($this->getlastPath($this->controllerpath));
        $name = str_replace($lastpath,null,$name);
        return $lastpath.'.'.parent::nameForRoute($action);
    }
}