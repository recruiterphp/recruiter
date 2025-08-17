<?php

declare(strict_types=1);

namespace Recruiter\SchedulePolicy;

use PHPUnit\Framework\TestCase;
use Timeless\Moment;

class CronTest extends TestCase
{
    /**
     * @dataProvider cronExpressions
     *
     * @throws \DateMalformedStringException
     */
    public function testCronCanBeExportedAndImportedWithoutDataLoss(string $cronExpression, string $expectedDate): void
    {
        $cron = new Cron($cronExpression, \DateTime::createFromFormat('Y-m-d H:i:s', '2019-01-15 15:00:00'));
        $cron = Cron::import($cron->export());

        $this->assertEquals(
            Moment::fromDateTime(new \DateTime($expectedDate)),
            $cron->next(),
            'calculated schedule time is: ' . $cron->next()->format(),
        );
    }

    /**
     * @return string[][]
     */
    public static function cronExpressions(): array
    {
        return [
            ['10 * * * *', '2019-01-15 15:10:00'],
            ['14 2 * * *', '2019-01-16 02:14:00'],
            ['18 10 15 * *', '2019-02-15 10:18:00'],
            ['18 10 15 8 *', '2019-08-15 10:18:00'],
            ['18 10 * * 2', '2019-01-22 10:18:00'],
        ];
    }
}
