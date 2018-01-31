<?php
/*
 * This file is part of the OpCart software.
 *
 * (c) 2018, ecentria group, Inc
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Deployer\Executor;

use Amp\Loop;
use Deployer\Console\Application;
use Deployer\Console\Output\Informer;
use Deployer\Exception\LogicException;
use Deployer\Exception\NonFatalException;
use Deployer\Host\Host;
use Deployer\Host\Localhost;
use Deployer\Task\AsyncTask;
use Deployer\Task\Context;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AsyncExecutor implements ExecutorInterface
{
    /**
     * @var InputInterface
     */
    private $input;

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @var Informer
     */
    private $informer;

    /**
     * @var Application
     */
    private $console;

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param Informer $informer
     * @param Application $console
     */
    public function __construct(InputInterface $input, OutputInterface $output, Informer $informer, Application $console)
    {
        $this->input = $input;
        $this->output = $output;
        $this->informer = $informer;
        $this->console = $console;
    }

    /**
     * @param AsyncTask[] $tasks
     * @param Host[] $hosts
     *
     * @throws LogicException
     */
    public function run($tasks, $hosts)
    {
        $localhost = new Localhost();
        Loop::run(function () use ($tasks, $localhost, $hosts) {
            foreach ($tasks as $task) {
                if (!$task instanceof AsyncTask) {
                    throw new LogicException('Only async tasks are supported');
                }
                $success = true;
                $this->informer->startTask($task);

                if ($task->isLocal()) {
                    yield from $task->run(new Context($localhost, $this->input, $this->output));
                } else {
                    foreach ($hosts as $host) {
                        if ($task->shouldBePerformed($host)) {
                            try {
                                yield from $task->run(new Context($host, $this->input, $this->output));
                            } catch (NonFatalException $exception) {
                                $success = false;
                                $this->informer->taskException($exception, $host);
                            }
                            $this->informer->endOnHost($host->getHostname());
                        }
                    }
                }

                if ($success) {
                    $this->informer->endTask($task);
                } else {
                    $this->informer->taskError();
                }
            }
        });
    }
}
