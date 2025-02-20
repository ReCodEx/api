<?php

namespace App\Helpers\MetaFormats;

// the string values have to match the return string of gettype()
// @codingStandardsIgnoreStart
enum PhpTypes: string
{
  case String = "string";
  case Int = "integer";
  case Double = "double";
  case Object = "object";
  case Null = "NULL";
  case Bool = "boolean";
}
// @codingStandardsIgnoreEnd
