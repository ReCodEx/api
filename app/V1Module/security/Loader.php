<?php

namespace App\Security;

use Nette\IOException;
use Nette\SmartObject;
use Nette\Utils\Arrays;
use Nette\Neon\Neon;
use ReflectionClass;

class Loader
{
    use SmartObject;

    private $rolesBuilder;

    private $authorizatorBuilder;

    private $aclModuleBuilder;

    private $loaded = false;

    private $configFilePath;

    private $aclInterfaces;

    private $hash;

    private $tempDirectory;

    private $userStorage;

    public function __construct($tempDirectory, $configFilePath, $aclInterfaces, UserStorage $userStorage)
    {
        $this->tempDirectory = $tempDirectory;
        $this->configFilePath = $configFilePath;
        $this->aclInterfaces = $aclInterfaces;
        $this->rolesBuilder = new RolesBuilder();
        $this->authorizatorBuilder = new AuthorizatorBuilder();
        $this->aclModuleBuilder = new ACLModuleBuilder();
        $this->userStorage = $userStorage;
        $this->hash = $this->calculateHash($this->configFilePath, $this->aclInterfaces);
    }

    private function calculateHash($configFilePath, $aclInterfaces)
    {
        $interfaceHashes = [];

        foreach ($aclInterfaces as $interface) {
            $reflection = new ReflectionClass($interface);
            $interfaceHashes[$interface] = sha1_file($reflection->getFileName());
        }

        $hash = sha1(
            serialize(
                [
                    "config" => sha1_file($configFilePath),
                    "interfaces" => $interfaceHashes
                ]
            )
        );

        return substr($hash, 0, 10);
    }

    private function loadGeneratedClasses()
    {
        if ($this->loaded) {
            return;
        }

        if (!is_dir($this->tempDirectory)) {
            @mkdir($this->tempDirectory); // @ - directory may already exist
        }

        $file = $this->tempDirectory . '/generated_classes_' . $this->hash . '.php';
        $lock = fopen($file . '.lock', 'c+');
        flock($lock, LOCK_EX);

        if (!is_file($file)) {
            $config = Neon::decode(file_get_contents($this->configFilePath));
            $content = "<?php\n";

            $roles = $this->rolesBuilder->build(
                Arrays::get($config, "roles"),
                $this->hash
            );
            $content .= (string)$roles;

            $authorizator = $this->authorizatorBuilder->build(
                $this->aclInterfaces,
                Arrays::get($config, "permissions"),
                $this->hash
            );

            $content .= (string)$authorizator;

            foreach ($this->aclInterfaces as $name => $interfaceName) {
                $module = $this->aclModuleBuilder->build($interfaceName, $name, $this->hash);
                $content .= "\n\n";
                $content .= (string)$module;
            }

            file_put_contents($file, $content);
        }

        flock($lock, LOCK_UN);

        if ((@include $file) === false) {
            throw new IOException("Could not read generated security classes");
        }

        $this->loaded = true;
    }

    public function loadRoles(): Roles
    {
        $this->loadGeneratedClasses();
        $class = $this->rolesBuilder->getClassName($this->hash);
        return new $class();
    }

    public function loadAuthorizator(PolicyRegistry $registry, Roles $roles): Authorizator
    {
        $this->loadGeneratedClasses();
        $class = $this->authorizatorBuilder->getClassName($this->hash);
        return new $class($registry, $roles);
    }

    public function loadACLModule($interfaceName, IAuthorizator $authorizator, ?Identity $identity = null)
    {
        $this->loadGeneratedClasses();
        $class = $this->aclModuleBuilder->getClassName($interfaceName, $this->hash);
        $module = new $class($this->userStorage, $authorizator, $identity);
        return $module;
    }
}
