<?php

namespace Lexroute;

use Illuminate\Console\Command;

class RouteMake extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'route:make {name?} {--a|api}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'make controller and update route';

    protected $type = ['web','api'];

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
        return $this->makeController();
    }

    protected function makeController(){
        if($this->argument('name') === null){
            $name = $this->ask('input new controller name');
        }else{
            $name = $this->argument('name');
        }
        $opt['name'] = $name;
       
        if($this->option('api') === true){
            $opt['--api'] = true;
            $type['-a'] = true;
        }else{
            $opt['--resource'] = true;
            $type['-a'] = false;
        }

        if($this->call('make:controller',$opt)===0){
            $this->call('route:update',$type);
        }

    }

    private function config(){
           return $this->config = (object) config('lexroute');
    }
}
