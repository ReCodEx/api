<?php

namespace App\Console;

use App\Model\Repository\RuntimeEnvironments;
use App\Model\Repository\Pipelines;
use App\Model\Repository\UploadedFiles;
use App\Model\Entity\RuntimeEnvironment;
use App\Model\Entity\Pipeline;
use App\Model\Entity\SupplementaryExerciseFile;
use App\Helpers\TmpFilesHelper;
use App\Helpers\FileStorageManager;
use App\Helpers\FileStorage\ZipFileStorage;
use App\Helpers\ExerciseConfig\Loader;
use App\Helpers\ExerciseConfig\Validator as ConfigValidator;
use DateTime;
use Exception;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Helper\QuestionHelper;

/**
 * Base class for all commands that gather helper methods.
 */
class BaseCommand extends Command
{
    /** @var InputInterface|null */
    protected $input = null;

    /** @var OutputInterface|null */
    protected $output = null;

    /** @var bool */
    protected $nonInteractive = false;

    /**
     * Wrapper that fetches an option value and converts it into DateTime.
     * If the option has invalid value, error is printed out and the exception is re-thrown.
     * @param string $name of the option
     * @return DateTime|null null if the option is missing
     */
    protected function getDateTimeOption(string $name): ?DateTime
    {
        $value = $this->input->getOption($name);
        try {
            return $value !== null ? new DateTime($value) : null;
        } catch (Exception $e) {
            $this->output->writeln("Value '$value' for option '$name' cannot be parsed as date/time.");
            throw $e;
        }
    }

    /**
     * Wrapper for confirmation question.
     * @param string $text message of the question
     * @param bool $default value when the query is confirmed hastily
     * @return bool true if the user confirmed the inquiry
     */
    protected function confirm(string $text, bool $default = false): bool
    {
        if (!$this->input || !$this->output) {
            throw new RuntimeException("The confirm() method may be invoked only when the command is executed.");
        }

        if (!empty($this->nonInteractive)) {
            return true; // assume "yes"
        }

        /** @var QuestionHelper */
        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion($text, $default);
        return $helper->ask($this->input, $this->output, $question);
    }

    /**
     * Local helper that convert numeric index into letter-based encoding.
     * (0 = a, ..., 25 = z, 26 = aa, 27 = ab, ...)
     * @param int $idx zero based index
     * @return string encoded representation
     */
    private static function indexToLetters(int $idx): string
    {
        $res = '';
        do {
            $letter = chr(($idx % 26) + ord('a'));
            $res = "$letter$res";
            $idx = (int)($idx / 26) - 1;
        } while ($idx >= 0);
        return $res;
    }

    /**
     * Perform a select inquery so the user chooses from given options.
     * @param string $text of the inquery
     * @param array $options to choose from
     * @param callable|null $renderer explicit to-string converter for options
     * @return mixed selected option value
     */
    protected function select(string $text, array $options, ?callable $renderer = null)
    {
        if (!$this->input || !$this->output) {
            throw new RuntimeException("The select() method may be invoked only when the command is executed.");
        }

        if (count($options) === 1) {
            return reset($options); // only one item to choose from
        }

        if ($this->nonInteractive) {
            throw new RuntimeException(
                "Unable preform the '$text' inquery in non-interactive mode. Operation aborted."
            );
        }

        // wrap the options into strings with a, b, c, d ... selection keys
        $internalOptions = [];
        $translateBack = [];
        foreach (array_values($options) as $idx => $option) {
            $key = self::indexToLetters($idx);
            $internalOptions[$key] = $renderer ? $renderer($option) : $option;
            $translateBack[$key] = $option;
        }

        // make the inquery
        QuestionHelper::disableStty();
        /** @var QuestionHelper */
        $helper = $this->getHelper('question');
        $question = new ChoiceQuestion($text, $internalOptions, 0);
        $question->setErrorMessage('Invalid input.');

        // translate the selection back to an option and report it
        $selectedKey = $helper->ask($this->input, $this->output, $question);
        return array_key_exists($selectedKey, $translateBack) ? $translateBack[$selectedKey] : null;
    }
}
