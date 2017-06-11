<?php
namespace App\Security;


use Nette\IOException;
use Nette\Utils\Arrays;
use Nette\Utils\Neon;

class Loader {
  private $authorizatorBuilder;

  private $aclModuleBuilder;

  private $loaded = FALSE;

  private $configFilePath;

  private $aclInterfaces;

  public function __construct($tempDirectory, $configFilePath, $aclInterfaces) {
    $this->tempDirectory = $tempDirectory;
    $this->configFilePath = $configFilePath;
    $this->aclInterfaces = $aclInterfaces;
    $this->authorizatorBuilder = new AuthorizatorBuilder();
    $this->aclModuleBuilder = new ACLModuleBuilder();
  }

  private function loadGeneratedClasses() {
    if ($this->loaded) {
      return;
    }

    if (!is_dir($this->tempDirectory)) {
      @mkdir($this->tempDirectory); // @ - directory may already exist
    }

    $file = $this->tempDirectory . '/generated_classes.php';
    $lock = fopen($file . '.lock', 'c+');
    flock($lock, LOCK_EX);

    if (!is_file($file)) {
      $config = Neon::decode(file_get_contents($this->configFilePath));
      $content = "<?php\n";

      $authorizator = $this->authorizatorBuilder->build(
        $this->aclInterfaces,
        Arrays::get($config, "roles"),
        Arrays::get($config, "permissions")
      );

      $content .= (string) $authorizator;

      foreach ($this->aclInterfaces as $name => $interfaceName) {
        $module = $this->aclModuleBuilder->build($interfaceName, $name);
        $content .= "\n\n";
        $content .= (string) $module;
      }

      file_put_contents($file, $content);
    }

    if ((@include $file) === FALSE) {
      throw new IOException("Could not read generated security classes");
    }

    flock($lock, LOCK_UN);
    $this->loaded = TRUE;
  }

  public function loadAuthorizator(PolicyRegistry $registry): Authorizator {
    $this->loadGeneratedClasses();
    $class = $this->authorizatorBuilder->getClassName();
    return new $class($registry);
  }

  public function loadACLModule($name, UserStorage $userStorage, IAuthorizator $authorizator) {
    $this->loadGeneratedClasses();
    $class = $this->aclModuleBuilder->getClassName($this->aclInterfaces[$name]);
    return new $class($userStorage, $authorizator);
  }
}