<?php
namespace Irbis\Interfaces;
use Irbis\Http\Request;


interface ConstraintInterface {
    public function validate (Request $request): ?HttpException;
}