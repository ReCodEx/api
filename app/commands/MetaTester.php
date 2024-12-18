<?php

namespace App\Console;

use App\Helpers\MetaFormats\FormatDefinitions\GroupFormat;
use App\Helpers\MetaFormats\FormatDefinitions\UserFormat;
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

    public function test(string $arg)
    {
        // $view = new TestView();
        // $view->endpoint([
        //     "id" => "0",
        //     "organizational" => false,
        // ], "0001");
        // // $view->get_user_info(0);

        // $format = new GroupFormat();
        // var_dump($format->checkIfAssignable("primaryAdminsIds", [ "10000000-2000-4000-8000-160000000000", "10000000-2000-4000-8000-160000000000" ]));

        $format = new UserFormat();
        var_dump($format->checkedAssign("email", "a@a.a.a"));
    }
}
