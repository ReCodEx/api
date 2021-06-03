<?php

use App\Helpers\Emails\EmailLocalizationHelper;
use Tester\Assert;
use Tester\TestCase;

require __DIR__ . "/../bootstrap.php";

class EmailHelpers extends TestCase
{
    private static function interval($locale, $y = 0, $m = 0, $d = 0, $h = 0, $i = 0, $s = 0)
    {
        $int = new DateInterval(join('', ['P', $y, 'Y', $m, 'M', $d, 'DT', $h, 'H', $i, 'M', $s, 'S']));
        return EmailLocalizationHelper::getDateIntervalLocalizedString($int, $locale);
    }

    public function testLocalizationDateInterval()
    {
        Assert::equal('1 rok', self::interval('cs', 1));
        Assert::equal('2 roky', self::interval('cs', 2));
        Assert::equal('7 let', self::interval('cs', 7));
        Assert::equal('1 rok', self::interval('cs', 1, 0, 3));
        Assert::equal('2 roky', self::interval('cs', 2, 0, 5));
        Assert::equal('7 let', self::interval('cs', 7, 0, 0, 1));
        Assert::equal('1 rok, 1 měsíc', self::interval('cs', 1, 1));
        Assert::equal('2 roky, 3 měsíce', self::interval('cs', 2, 3));
        Assert::equal('7 let, 8 měsíců', self::interval('cs', 7, 8));
        Assert::equal('1 den, 3 hodiny', self::interval('cs', 0, 0, 1, 3));
        Assert::equal('4 dny, 5 hodin', self::interval('cs', 0, 0, 4, 5));
        Assert::equal('29 dní, 1 hodina', self::interval('cs', 0, 0, 29, 1));
        Assert::equal('2 hodiny', self::interval('cs', 0, 0, 0, 2, 0, 4));
        Assert::equal('1 minuta, 1 vteřina', self::interval('cs', 0, 0, 0, 0, 1, 1));
        Assert::equal('3 minuty, 3 vteřiny', self::interval('cs', 0, 0, 0, 0, 3, 3));
        Assert::equal('6 minut, 6 vteřin', self::interval('cs', 0, 0, 0, 0, 6, 6));
        Assert::equal('27 vteřin', self::interval('cs', 0, 0, 0, 0, 0, 27));

        Assert::equal('1 year', self::interval('en', 1));
        Assert::equal('2 years', self::interval('en', 2));
        Assert::equal('1 year', self::interval('en', 1, 0, 3));
        Assert::equal('2 years', self::interval('en', 2, 0, 5));
        Assert::equal('1 year, 1 month', self::interval('en', 1, 1));
        Assert::equal('2 years, 3 months', self::interval('en', 2, 3));
        Assert::equal('1 day, 3 hours', self::interval('en', 0, 0, 1, 3));
        Assert::equal('4 days, 1 hour', self::interval('en', 0, 0, 4, 1));
        Assert::equal('2 hours', self::interval('en', 0, 0, 0, 2, 0, 4));
        Assert::equal('1 minute, 1 second', self::interval('en', 0, 0, 0, 0, 1, 1));
        Assert::equal('3 minutes, 3 seconds', self::interval('en', 0, 0, 0, 0, 3, 3));
        Assert::equal('27 seconds', self::interval('en', 0, 0, 0, 0, 0, 27));
    }
}

(new EmailHelpers())->run();
