<?php
namespace App\PHPStan;

use Nette\Utils\Arrays;
use Nette;
use Nette\Utils\Strings;
use PHPStan\Analyser\NameScope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Type;
use PHPStan;

class MagicMethodReflection implements MethodReflection
{
  /** @var ClassReflection */
  private $declaringClass;

  /** @var string */
  private $name;

  /** @var string */
  private $type;

  public function __construct(ClassReflection $declaringClass, string $name)
  {
    $this->declaringClass = $declaringClass;
    $this->name = $name;

    list($name, $type) = self::parseMethod($declaringClass, $name);
    $this->type = $type;
  }

  public static function parseMethod(ClassReflection $class, string $name)
  {
    $reflection = Nette\Reflection\ClassType::from($class->getName());
    $methodAnnotations = Arrays::get($reflection->getAnnotations(), "method", []);

    foreach ($methodAnnotations as $annotation) {
      $parts = Strings::split($annotation, '#\s+#');

      if (Strings::contains($parts[0], "(")) {
        $methodName = Strings::before($parts[0], "(");
        $type = new PHPStan\Type\NullType();
      } else if (Strings::contains($parts[1], "(")) {
        $methodName = Strings::before($parts[1], "(");
        $type = $parts[0] === "void"
          ? new PHPStan\Type\NullType()
          : PHPStan\Type\TypehintHelper::getTypeObjectFromTypehint($parts[0], $class->getName(), new NameScope($reflection->getNamespaceName(), []));
      } else {
        continue;
      }

      if ($name === $methodName) {
        return [$name, $type];
      }
    }

    return NULL;
  }

  public function getDeclaringClass(): ClassReflection
  {
    return $this->declaringClass;
  }

  public function isStatic(): bool
  {
    return FALSE;
  }

  public function isPrivate(): bool
  {
    return FALSE;
  }

  public function isPublic(): bool
  {
    return TRUE;
  }

  public function getName(): string
  {
    return $this->name;
  }

  /**
   * @return \PHPStan\Reflection\ParameterReflection[]
   */
  public function getParameters(): array
  {
    if (Strings::startsWith($this->name, "get") || Strings::startsWith($this->name, "is")) {
      return [];
    }

    return [
      new MagicMethodParameterReflection("param", $this->type)
    ];
  }

  public function isVariadic(): bool
  {
    return FALSE;
  }

  public function getReturnType(): Type
  {
    return $this->type;
  }

  public function getPrototype(): MethodReflection
  {
    return $this;
  }
}
