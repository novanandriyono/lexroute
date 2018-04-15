<?php

namespace Lexroute;
use Lexroute\Contracts\LexrouteException as BaseLexrouteException;
use Exception;

class LexrouteException extends Exception implements BaseLexrouteException
{

  	public function checkError(){
  		return $this->setError();
  	}

  	protected function setError(){
  		try{
            if(app()->runningInConsole() !== true || isset($_SERVER['SERVER_NAME'])){
              if(\App::environment('local') !== true){
                $message = " must be run in local";
              }
              $message = "app must be run in console";
            }
            if(isset($message)){
              throw new LexrouteException($message);
            }
        }
        catch(LexrouteException $e) {
	  		 if(app()->runningInConsole() !== true){
           throw $e;
         }
        }
  	}


}
