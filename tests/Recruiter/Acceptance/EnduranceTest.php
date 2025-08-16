<?php

declare(strict_types=1);

namespace Recruiter\Acceptance;

use Eris;
use Eris\Generator;
use Eris\Listener;
use Recruiter\Concurrency\Timeout;
use Recruiter\Job\Repository;
use Timeless as T;

/**
 * @group long
 */
class EnduranceTest extends BaseAcceptanceTestCase
{
    use Eris\TestTrait;

    private Repository $jobRepository;
    private string $actionLog;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->jobRepository = new Repository($this->recruiterDb);
        $this->actionLog = '/tmp/actions.log';
        $this->files[] = $this->actionLog;
    }

    public function testNotWithstandingCrashesJobsAreEventuallyPerformed(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generator\bind(
                    Generator\choose(1, 4),
                    fn ($workers) => Generator\tuple(
                        Generator\constant($workers),
                        Generator\seq(Generator\oneOf(
                            Generator\map(
                                function ($durationAndTag) {
                                    [$duration, $tag] = $durationAndTag;

                                    return ['enqueueJob', $duration, $tag];
                                },
                                Generator\tuple(
                                    Generator\nat(),
                                    Generator\elements(['generic', 'fast-lane']),
                                ),
                            ),
                            Generator\map(
                                fn ($workerIndex) => ['restartWorkerGracefully', $workerIndex],
                                Generator\choose(0, $workers - 1),
                            ),
                            Generator\map(
                                fn ($workerIndex) => ['restartWorkerByKilling', $workerIndex],
                                Generator\choose(0, $workers - 1),
                            ),
                            Generator\constant('restartRecruiterGracefully'),
                            Generator\constant('restartRecruiterByKilling'),
                            Generator\map(
                                fn ($milliseconds) => ['sleep', $milliseconds],
                                Generator\choose(1, 1000),
                            ),
                        )),
                    ),
                ),
            )
            ->hook(Listener\log('/tmp/recruiter-test-iterations.log'))
            ->hook(Listener\collectFrequencies())
            ->disableShrinking()
            ->then(function ($tuple): void {
                [$workers, $actions] = $tuple;
                $this->clean();
                $this->start($workers);
                foreach ($actions as $action) {
                    $this->logAction($action);
                    if (is_array($action)) {
                        $arguments = $action;
                        $method = array_shift($arguments);
                        call_user_func_array(
                            [$this, $method],
                            $arguments,
                        );
                    } else {
                        $this->$action();
                    }
                }

                $estimatedTime = max(count($actions) * 4, 60);
                Timeout::inSeconds(
                    $estimatedTime,
                    fn () => "all $this->jobs jobs to be performed. Now is " . date('c') . ' Logs: ' . $this->files(),
                )
                    ->until(fn () => $this->jobRepository->countArchived() === $this->jobs)
                ;

                $at = T\now();
                $statistics = $this->recruiter->statistics($tag = null, $at);
                $this->assertInvariantsOnStatistics($statistics);
                // TODO: remove duplication
                $statisticsByTag = [];
                $cumulativeThroughput = 0;
                foreach (['generic', 'fast-lane'] as $tag) {
                    $statisticsByTag[$tag] = $this->recruiter->statistics($tag, $at);
                    $this->assertInvariantsOnStatistics($statisticsByTag[$tag]);
                    $cumulativeThroughput += $statisticsByTag[$tag]['throughput']['value'];
                }
                // TODO: add tolerance
                $this->assertEquals($statistics['throughput']['value'], $cumulativeThroughput);
            })
        ;
    }

    private function logAction($action)
    {
        file_put_contents(
            $this->actionLog,
            sprintf(
                '[ACTIONS][PHPUNIT][%s] %s' . PHP_EOL,
                date('c'),
                json_encode($action),
            ),
            FILE_APPEND,
        );
    }

    protected function sleep($milliseconds)
    {
        usleep($milliseconds * 1000);
    }

    protected function assertInvariantsOnStatistics($statistics)
    {
        $this->assertEquals(0, $statistics['jobs']['queued']);
        $this->assertEquals(0, $statistics['jobs']['zombies']);
        $this->assertGreaterThanOrEqual(0.0, $statistics['throughput']['value']);
        $this->assertGreaterThanOrEqual(0.0, $statistics['throughput']['value_per_second']);
        $this->assertGreaterThanOrEqual(0.0, $statistics['latency']['average']);
        $this->assertLessThan(120.0, $statistics['latency']['average']);
        $this->assertGreaterThanOrEqual(0.0, $statistics['execution_time']['average']);
        $this->assertLessThan(1.0, $statistics['execution_time']['average']);
    }
}
