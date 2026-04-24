<?php
namespace Irbis\Routing;
use Irbis\Routing\Constraint;
use Irbis\Http\Request;
use Irbis\Exceptions\HttpException;


class MethodConstraint extends Constraint 
{
    public function validate(Request $request): ?HttpException 
    {
        $allowed = array_map(fn($method) => $method->value, $this->action->methods);
        if ($allowed && !in_array($request->method->value, $allowed)) {
            return new HttpException(405, "Method {$request->method->value} Not Allowed");
        }
        return null;
    }
}