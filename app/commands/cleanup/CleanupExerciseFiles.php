<?php

namespace App\Console;

use App\Model\Repository\ExerciseFiles;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'db:cleanup:exercise-files',
    description: 'Remove unused exercise files (only DB records are removed).'
)]
class CleanupExercisesFiles extends Command
{
    /**
     * @var ExerciseFiles
     */
    private $files;

    public function __construct(ExerciseFiles $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    private function removeUnusedFiles(OutputInterface $output)
    {
        $unused = $this->files->findUnused();
        foreach ($unused as $file) {
            $this->files->remove($file);
        }

        $output->writeln(sprintf("Removed %d unused exercise file records.", count($unused)));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->removeUnusedFiles($output);
        return 0;
    }
}
