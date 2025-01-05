<?php

namespace App\Console;

use App\Helpers\MetaFormats\AnnotationToAttributeConverter;
use App\Helpers\MetaFormats\FormatDefinitions\GroupFormat;
use App\Helpers\MetaFormats\FormatDefinitions\UserFormat;
use App\Helpers\MetaFormats\MetaFormatHelper;
use App\Helpers\Swagger\AnnotationHelper;
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

        // $format = new UserFormat();
        // var_dump($format->checkedAssign("email", "a@a.a.a"));

        $inDir = __DIR__ . "/../V1Module/presenters";
        $outDir = __DIR__ . "/../V1Module/presenters2";

        // create output folder
        if (!is_dir($outDir)) {
            mkdir($outDir);

            // copy base subfolder
            $inBaseDir = $inDir . "/base";
            $outBaseDir = $outDir . "/base";
            mkdir($outBaseDir);
            $baseFilenames = scandir($inBaseDir);
            foreach ($baseFilenames as $filename) {
                if (!str_ends_with($filename, ".php")) {
                    continue;
                }

                copy($inBaseDir . "/" . $filename, $outBaseDir . "/" . $filename);
            }
        }

        $filenames = scandir($inDir);
        foreach ($filenames as $filename) {
            if (!str_ends_with($filename, "Presenter.php")) {
                continue;
            }

            $filepath = $inDir . "/" . $filename;
            $newContent = AnnotationToAttributeConverter::convertFile($filepath);
            $newFile = fopen($outDir . "/" . $filename, "w");
            fwrite($newFile, $newContent);
            fclose($newFile);
        }

        // $reflection = AnnotationHelper::getMethod("App\V1Module\Presenters\RegistrationPresenter", "actionCreateAccount");
        // $attrs = MetaFormatHelper::extractRequestParamData($reflection);
        // var_dump($attrs);
    }
}
