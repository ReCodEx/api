<?php

namespace App\Helpers\JobConfig;

use Nette\Utils\Arrays;
use App\Helpers\Yaml;

/**
 * Sandbox configuration holder contains mainly name of used sandbox and limits
 * for specific hardware groups. Limits can be also removed or set
 * to another ones.
 */
class SandboxConfig
{
    /** Sandbox name key */
    public const NAME_KEY = "name";
    /** Stdin config key */
    public const STDIN_KEY = "stdin";
    /** Stdout config key */
    public const STDOUT_KEY = "stdout";
    /** Stderr config key */
    public const STDERR_KEY = "stderr";
    /** Stderr-to-stdout config key */
    public const STDERR_TO_STDOUT_KEY = "stderr-to-stdout";
    /** Output config key */
    public const OUTPUT_KEY = "output";
    /** Carbon copy stdout key */
    public const CARBONCOPY_STDOUT_KEY = "carboncopy-stdout";
    /** Carbon copy stderr key */
    public const CARBONCOPY_STDERR_KEY = "carboncopy-stderr";
    /** Change directory key */
    public const CHDIR_KEY = "chdir";
    /** Working directory key */
    public const WORKING_DIRECTORY_KEY = "working-directory";
    /** Limits collection key */
    public const LIMITS_KEY = "limits";

    /** @var string Sandbox name */
    private $name = "";
    /** @var string|null Standard input redirection file */
    private $stdin = null;
    /** @var string|null Standard output redirection file */
    private $stdout = null;
    /** @var string|null Standard error redirection file */
    private $stderr = null;
    /** @var bool Output from stderr will be redirected to stdout */
    private $stderrToStdout = false;
    /** @var bool Output from stdout and stderr will be written to result yaml */
    private $output = false;
    /** @var string|null Standard output carbon copy file */
    private $carboncopyStdout = null;
    /** @var string|null Standard error carbon copy file */
    private $carboncopyStderr = null;
    /** @var string|null Change directory */
    protected $chdir = null;
    /** @var string|null Working directory */
    protected $workingDirectory = null;
    /** @var array List of limits */
    private $limits = [];
    /** @var array Additional data */
    private $data = [];

    /**
     * Get sandbox name.
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set name of the used sandbox.
     * @param string $name
     * @return $this
     */
    public function setName(string $name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Return standard input redirection file.
     * @return string|null
     */
    public function getStdin()
    {
        return $this->stdin;
    }

    /**
     * Set input redirection file.
     * @param string $stdin
     * @return $this
     */
    public function setStdin($stdin)
    {
        $this->stdin = $stdin;
        return $this;
    }

    /**
     * Return standard output redirection file.
     * @return string|null
     */
    public function getStdout()
    {
        return $this->stdout;
    }

    /**
     * Set output redirection file.
     * @param string $stdout
     * @return $this
     */
    public function setStdout($stdout)
    {
        $this->stdout = $stdout;
        return $this;
    }

    /**
     * Get standard error redirection file.
     * @return string|null
     */
    public function getStderr()
    {
        return $this->stderr;
    }

    /**
     * Set error redirection file.
     * @param string $stderr
     * @return $this
     */
    public function setStderr($stderr)
    {
        $this->stderr = $stderr;
        return $this;
    }

    /**
     * Is redirection of stderr to stdout activated or not.
     * @return bool
     */
    public function getStderrToStdout(): bool
    {
        return $this->stderrToStdout;
    }

    /**
     * Set redirection of stderr to stdout.
     * @param bool $stderrToStdout
     * @return $this
     */
    public function setStderrToStdout(bool $stderrToStdout)
    {
        $this->stderrToStdout = $stderrToStdout;
        return $this;
    }

    /**
     * Get output to stdout and stderr.
     * @return bool
     */
    public function getOutput(): bool
    {
        return $this->output;
    }

    /**
     * Set output to stdout and stderr.
     * @param bool $output
     * @return $this
     */
    public function setOutput(bool $output)
    {
        $this->output = $output;
        return $this;
    }

    /**
     * Return standard output carbon copy file.
     * @return string|null
     */
    public function getCarboncopyStdout()
    {
        return $this->carboncopyStdout;
    }

    /**
     * Set output carbon copy file.
     * @param string $stdout
     * @return $this
     */
    public function setCarboncopyStdout($stdout)
    {
        $this->carboncopyStdout = $stdout;
        return $this;
    }

    /**
     * Get standard error carbon copy file.
     * @return string|null
     */
    public function getCarboncopyStderr()
    {
        return $this->carboncopyStderr;
    }

    /**
     * Set error carbon copy file.
     * @param string $stderr
     * @return $this
     */
    public function setCarboncopyStderr($stderr)
    {
        $this->carboncopyStderr = $stderr;
        return $this;
    }

    /**
     * Get directory in which sand-boxed program will be executed.
     * @return string|null
     */
    public function getChdir()
    {
        return $this->chdir;
    }

    /**
     * Set directory to which sandbox will change working directory.
     * @param string $chdir working directory
     * @return $this
     */
    public function setChdir($chdir)
    {
        $this->chdir = $chdir;
        return $this;
    }

    /**
     * Get directory in which will be base working directory for executed program.
     * @return string|null
     */
    public function getWorkingDirectory()
    {
        return $this->workingDirectory;
    }

    /**
     * Set directory to which will be base working directory for executed program.
     * @param string $workingDirectory working directory
     * @return $this
     */
    public function setWorkingDirectory($workingDirectory)
    {
        $this->workingDirectory = $workingDirectory;
        return $this;
    }

    /**
     * Gets limits as array.
     * @return Limits[]
     */
    public function getLimitsArray(): array
    {
        return $this->limits;
    }

    /**
     * Does the task config have limits for given hardware group?
     * @param string $hardwareGroupId identification of hardware group
     * @return bool
     */
    public function hasLimits(string $hardwareGroupId): bool
    {
        return isset($this->limits[$hardwareGroupId]);
    }

    /**
     * Get the configured limits for a specific hardware group.
     * @param string $hardwareGroupId Hardware group ID
     * @return Limits|null Limits for the specified hardware group
     */
    public function getLimits(string $hardwareGroupId): ?Limits
    {
        return Arrays::get($this->limits, $hardwareGroupId, null);
    }

    /**
     * Set limits for a specific hardware group
     * @param Limits|null $limits The limits
     * @return void
     */
    public function setLimits(?Limits $limits)
    {
        if (!$limits) {
            return;
        }
        $this->limits[$limits->getId()] = $limits;
    }

    /**
     * Set limits of a given HW group to undefined, which basically means
     * that there are no more limits anymore.
     * @param string $hardwareGroupId Hardware group ID
     * @return void
     */
    public function removeLimits(string $hardwareGroupId)
    {
        $this->setLimits(new UndefinedLimits($hardwareGroupId));
    }

    /**
     * Get additional data.
     * Needed for forward compatibility.
     * @return array
     */
    public function getAdditionalData(): array
    {
        return $this->data;
    }

    /**
     * Set additional data, which cannot be parsed into structure.
     * Needed for forward compatibility.
     * @param array $data
     * @return $this
     */
    public function setAdditionalData(array $data)
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Creates and returns properly structured array representing this object.
     * @return array
     */
    public function toArray(): array
    {
        $data = $this->data;
        $data[self::NAME_KEY] = $this->name;
        if (!empty($this->stdin)) {
            $data[self::STDIN_KEY] = $this->stdin;
        }
        if (!empty($this->stdout)) {
            $data[self::STDOUT_KEY] = $this->stdout;
        }
        if (!empty($this->stderr)) {
            $data[self::STDERR_KEY] = $this->stderr;
        }
        if ($this->stderrToStdout) {
            $data[self::STDERR_TO_STDOUT_KEY] = $this->stderrToStdout;
        }
        if ($this->output) {
            $data[self::OUTPUT_KEY] = $this->output;
        }
        if (!empty($this->carboncopyStdout)) {
            $data[self::CARBONCOPY_STDOUT_KEY] = $this->carboncopyStdout;
        }
        if (!empty($this->carboncopyStderr)) {
            $data[self::CARBONCOPY_STDERR_KEY] = $this->carboncopyStderr;
        }
        if (!empty($this->chdir)) {
            $data[self::CHDIR_KEY] = $this->chdir;
        }
        if (!empty($this->workingDirectory)) {
            $data[self::WORKING_DIRECTORY_KEY] = $this->workingDirectory;
        }

        if (!empty($this->limits)) {
            $data[self::LIMITS_KEY] = [];
            foreach ($this->limits as $limit) {
                $data[self::LIMITS_KEY][] = $limit->toArray();
            }
        }

        return $data;
    }

    /**
     * Serialize the config.
     * @return string
     */
    public function __toString(): string
    {
        return Yaml::dump($this->toArray());
    }
}
