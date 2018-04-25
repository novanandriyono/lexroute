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
          if(app()->runningInConsole() !== true){
            $message = 'app must be run in artisan console';
          }
          if(isset($_SERVER['SERVER_NAME']) === true){
            $message = 'app must be run in artisan console';
          }
          if(\App::environment('local') !== true){
            $message = "app must be run in local env";
          }
          if(isset($message) === true){
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
