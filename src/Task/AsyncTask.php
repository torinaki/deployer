<?php
/*
 * This file is part of the OpCart software.
 *
 * (c) 2018, ecentria group, Inc
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Deployer\Task;

use function Amp\call;

class AsyncTask extends Task
{
    /**
     * @param Context $context
     *
     * @return \Generator
     */
    public function run(Context $context)
    {
        Context::push($context);

        // Call task
        yield call($this->getCallback(), $context);

        if ($this->isOnce()) {
            $this->setHasRun(true);
        }

        // Clear working_path
        if ($context->getConfig() !== null) {
            $context->getConfig()->set('working_path', false);
        }

        Context::pop();
    }
}
