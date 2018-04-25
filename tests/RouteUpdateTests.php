<?php
namespace Tests;
use PHPUnit\Framework\TestCase;

class RouteUpdateTests extends TestCase
{
	public function testone()
    {
         $stub = $this->createMock(\Lexroute\RouteUpdate::class);
        $stub->method('handle')->willReturn(null);
        $this->assertSame(null, $stub->doSomething());
    }
}