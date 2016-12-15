<?php
namespace App\PHPStan;
use Nette\Utils\ObjectMixin;
use PHPStan;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;

class MagicMethodClassReflectionExtension implements PHPStan\Reflection\MethodsClassReflectionExtension
{
  public function hasMethod(ClassReflection $classReflection, string $methodName): bool
  {
    return MagicMethodReflection::parseMethod($classReflection, $methodName) !== NULL;
  }

  public function getMethod(ClassReflection $classReflection, string $methodName): MethodReflection
  {
    return new MagicMethodReflection($classReflection, $methodName);
  }
}