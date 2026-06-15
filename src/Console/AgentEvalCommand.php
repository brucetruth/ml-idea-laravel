<?php

declare(strict_types=1);

namespace ML\IDEA\Laravel\Console;

use Illuminate\Console\Command;
use ML\IDEA\Laravel\ToolRoutingAgentManager;
use ML\IDEA\RAG\Agents\AgentEvalHarness;

final class AgentEvalCommand extends Command
{
    protected $signature = 'mlidea:agent-eval
                            {fixture : Path to the eval JSON fixture}
                            {--min-pass-rate=1.0 : Minimum pass rate required to exit successfully}';

    protected $description = 'Run ml-idea agent routing eval fixtures against the configured Laravel agent';

    public function handle(ToolRoutingAgentManager $manager): int
    {
        $fixture = (string) $this->argument('fixture');
        if (!is_file($fixture)) {
            $this->error(sprintf('Fixture not found: %s', $fixture));

            return self::FAILURE;
        }

        $harness = new AgentEvalHarness();
        $cases = $harness->loadCasesFromJson($fixture);
        $results = $harness->run($manager->make(), $cases);
        $summary = $harness->summarize($results);

        foreach ($results as $result) {
            $status = $result->passed ? 'PASS' : 'FAIL';
            $this->line(sprintf('[%s] %s', $status, $result->name));
            if (!$result->passed && $result->message !== '') {
                $this->line('  ' . $result->message);
            }
        }

        $this->newLine();
        $this->info(sprintf(
            'Summary: %d/%d passed (pass rate %.2f%%)',
            $summary['passed'],
            $summary['total'],
            $summary['pass_rate'] * 100
        ));

        $minPassRate = (float) $this->option('min-pass-rate');
        if ($summary['pass_rate'] < $minPassRate) {
            $this->error(sprintf('Pass rate %.4f is below required minimum %.4f.', $summary['pass_rate'], $minPassRate));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
