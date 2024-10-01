<?php

namespace App\Console;

use App\Helpers\Notifications\ReviewsEmailsSender;
use App\Model\Repository\AssignmentSolutions;
use App\Model\Entity\Group;
use App\Model\Entity\User;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use DateTime;

class GenerateSwagger extends Command
{
    protected static $defaultName = 'swagger:generate';

    protected function configure()
    {
        $this->setName(self::$defaultName)->setDescription(
            'Generate a swagger specification file from existing code.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
      $output->writeln('TEST');
      return Command::SUCCESS;
    }
}
