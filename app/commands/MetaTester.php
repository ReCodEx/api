<?php

namespace App\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use App\Model\View\TestView;

///TODO: this command is debug only, delete it
class MetaTester extends Command
{
    protected static $defaultName = 'meta:test';

    protected function configure()
    {
        $this->setName(self::$defaultName)->setDescription(
            'Test the meta views.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->test("a");
        return Command::SUCCESS;
    }

    function test(string $arg)
    {
        $view = new TestView();
        $view->endpoint([
            "id" => "0",
            "organizational" => false,
        ], "0001", true);
        #$view->get_user_info(0);
    }
}
