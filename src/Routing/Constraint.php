<?php
namespace Irbis\Routing;
use Throwable;
use Irbis\Routing\Action;
use Irbis\Exceptions\HttpException;
use Irbis\Http\Request;


abstract class Constraint 
{
    public readonly Action $action;

    public function __construct (Action $action) 
    {
        $this->action = $action;
    }

    abstract public function validate(Request $request): ?HttpException;
}