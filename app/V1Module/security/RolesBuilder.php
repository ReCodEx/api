<?php

namespace App\Security;

use Nette\PhpGenerator\ClassType;
use Nette\Utils\Arrays;

class RolesBuilder
{

    public function getClassName($uniqueId)
    {
        return "RolesImpl_" . $uniqueId;
    }

    public function build($roles, $uniqueId): ClassType
    {
        $class = new ClassType($this->getClassName($uniqueId));
        $class->setExtends(Roles::class);

        $setup = $class->addMethod("setup");
        $setup->setVisibility("public");

        foreach ($roles as $role) {
            $setup->addBody(
                '$this->addRole(?, [...?]);',
                [
                    $role["name"],
                    (array)Arrays::get($role, "parents", []),
                ]
            );
        }

        return $class;
    }
}
