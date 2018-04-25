<?php
namespace Lexroute\Generator;
use Lexroute\Contracts\Generator\Generator as GeneratorInterface;

class Generator implements GeneratorInterface
{

	protected $lists,$controllerpath,$middleware,$translations;

	function __construct($lists = [], $controllerpath=null, $middleware=[], $translations = [])
	{
		$this->lists = $lists;
		$this->controllerpath = $controllerpath;
		$this->middleware = $middleware;
		$this->translations = $translations;
	}

	public function web(){
		return $this->webgenerator();
	}

    public function api(){
        $generator = new ApiRouteGenerator($this->lists,$this->controllerpath,$this->middleware);
        return $generator->api();
    }

	protected function webgenerator(){
        $lists = $this->lists;
        $controllerpath = $this->controllerpath;
        $routers = [];
        for ($i=0; $i < count($lists); $i++){
            $action = $lists[$i];
            $act = str_replace($controllerpath.DIRECTORY_SEPARATOR,null,$action);
            $name = $this->nameForRoute($action);
            $url = $this->uriForRoute($action);
            $method = $this->methodForRoute($name);
            if(is_array($method)){
                $routers[$name] = "Route::match(['".$method[0]."','".$method[1]."'],";
            }else{
                $routers[$name] = "Route::".$method."(";
            }
			if(count($this->translations) !== 0){
            $routers[$name] .= "__('";
            $routers[$name] .= $this->translation.'.'.$name;
            $routers[$name] .= "','";
            $routers[$name] .= $url;
            $routers[$name] .= "'),";
			}else{
            $routers[$name] .= "'";
            $routers[$name] .= $url;
            $routers[$name] .= "',";
            }
            $routers[$name] .= "'".$act."'";
            $routers[$name] .= ")";
            if(count($this->middleware) !== 0){
            $routers[$name] .= "->middleware('".implode("','", $this->middleware)."')";
            }
            $routers[$name] .= "->name('".$name."')";

        }
        return $routers;
    }


    protected function uriForRoute($action){
        $replace = [
            strtolower($this->controllerpath),
            'controller@',
        ];
        $newStr = [
            null,
            DIRECTORY_SEPARATOR
        ];
        $action = strtolower($action);
        $action = str_replace($replace,$newStr,$action);
        $paths = explode(DIRECTORY_SEPARATOR, $action);
        //$paths = $this->replaceParrent($paths);
        if(strpos($action,'index') !== FALSE){
        	$paths = $this->makeISUrl($paths);
        }elseif(strpos($action,'show') !== FALSE){
        	$paths = $this->makeSUDUrl($paths);
        }elseif(strpos($action,'create') !== FALSE){
        	$paths = $this->makeCreateUrl($paths);
        }elseif(strpos($action,'edit') !== FALSE){
        	$paths = $this->makeEditUrl($paths);
        }elseif(strpos($action,'update') !== FALSE){
            $paths = $this->makeSUDUrl($paths);
        }elseif(strpos($action,'store') !== FALSE){
            $paths = $this->makeISUrl($paths);
        }elseif(strpos($action,'destroy') !== FALSE){
         	$paths = $this->makeSUDUrl($paths);
        }elseif(strpos($action,'delete') !== FALSE){
            $paths = $this->makeSUDUrl($paths);
        }else{
            // unset($paths);
            // $this->error();
        }

        $paths = implode(DIRECTORY_SEPARATOR,$paths);
        $action = $paths;
        return str_replace(DIRECTORY_SEPARATOR,'/',$action);
    }

    protected function nameForRoute($action){
        $pattern = strtolower($this->normaldash($this->controllerpath)).".";
        $replace = [
            $pattern,
            'controller@',
            DIRECTORY_SEPARATOR,
            strtolower($this->getlastPath($this->controllerpath)),
        ];
        $newStr = [
            null,
            '.',
            null,
            null
        ];
        $action = strtolower($this->normaldash($action));
        $action = str_replace($replace,$newStr,$action);
        $action = $this->fixName($action);
        return strtolower($action);
    }


	/**
	* Create a new Show Update Delete and Destroy url instance.
	*
	* @return void
	*/

	protected function methodForRoute($action){
        if(strpos($action,'index') !== false){
            $action ='get';
        }elseif(strpos($action,'edit') !== false ){
            $action ='get';
        }elseif(strpos($action,'show') !== false){
            $action ='get';
        }elseif(strpos($action,'create') !== false){
            $action ='get';
        }elseif(strpos($action,'get') !== false){
            $action ='get';
        }elseif(strpos($action,'store') !== false){
            $action ='post';
        }elseif(strpos($action,'update') !== false){
            $action = ['put','patch'];
        }elseif(strpos($action,'match') !== false){
            $action = ['put','patch'];
        }elseif(strpos($action,'patch') !== false){
            $action ='patch';
        }elseif(strpos($action,'put') !== false){
            $action ='put';
        }elseif(strpos($action,'delete') !== false){
            $action ='delete';
        }elseif(strpos($action,'destroy') !== false){
            $action ='delete';
        }else{
           $action ='get';
        }
        return $action;
    }

	protected function fixName($action){
		$action = explode('.',$action);
		$action = $this->replaceParrent($action);
		return $this->implodeDots($action);
	}

	/**
	* Create a new Show Update Delete and Destroy url instance.
	*
	* @return void
	*/

	protected function makeSUDUrl($paths = []){
		if($paths[0]===""){
			unset($paths[0]);
		}
		array_pop($paths);
		$paths = $this->replaceParrent($paths);
		$end = "{".str_singular(end($paths))."}";
		$paths[] = $end;
		return $paths;
	}

	/**
	* Create a new Edit url instance.
	*
	* @return void
	*/

	protected function makeEditUrl($paths = []){
		if($paths[0]===""){
			unset($paths[0]);
		}
		array_pop($paths);
		$paths = $this->replaceParrent($paths);
		$end = "{".str_singular(end($paths))."}";
		$paths[] = $end;
		$paths[]= 'edit';
		return $paths;
	}

	/**
	* Create a new Index adn Store url instance.
	*
	* @return void
	*/

	protected function makeISUrl($paths = []){
		if($paths[0]===""){
			unset($paths[0]);
		}
		array_pop($paths);
		$paths = $this->replaceParrent($paths);
		return $paths;
	}

	/**
	* Create a new Index adn Store url instance.
	*
	* @return void
	*/

	protected function makeCreateUrl($paths = []){
		if($paths[0]===""){
			unset($paths[0]);
		}
		$paths = $this->replaceParrent($paths);
		return $paths;
	}

	/**
	* Replace Parent.
	*
	* @return void
	*/

	protected function replaceParrent($paths = []){
		$paths = array_values($paths);
		$firstpath = $paths[0];
		for ($i=0; $i < count($paths); $i++) { 
			$path = $paths[$i];
            if($path !== $firstpath){
                if(str_contains($path,$firstpath) === true){
					$paths[$i] = str_replace($firstpath,null,$path);
				}
			}
		}
		return $paths;
	}

	protected function normaldash($str = null){
        return str_replace('\\','.',str_replace('/','.',$str));
    }

    protected function newdash($str = null){
        return str_replace('.','/',$this->normaldash($str));
    }

    protected function getlastPath($str = null){
        $str = $this->normaldash($str);
        $str = explode('.', $str);
        return end($str);
    }

    protected function removeLastPath($str = null){
        $str = $this->normaldash($str);
        $str = $this->explodeDots($str);
        array_pop($str);
        return $this->implodeDots($str);
    }

    protected function explodeDots($str = null){
        return explode('.', $str);
    }

    protected function implodeDots($str = null){
        return implode('.', $str);
    }
}