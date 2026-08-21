<?php

declare(strict_types=1);

namespace Gember\EventSourcingSymfonyBundle\Command;

use Gember\EventSourcing\Outbox\Processor\OutboxProcessor;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'gember:outbox:process',
    description: 'Process pending outbox messages',
)]
final class ProcessOutboxCommand extends Command implements SignalableCommandInterface
{
    private bool $shouldStop = false;

    public function __construct(
        private readonly OutboxProcessor $outboxProcessor,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Max messages to process per batch', 100);
        $this->addOption('watch', 'w', InputOption::VALUE_OPTIONAL, 'Continuously poll for messages with interval in milliseconds', false);
        $this->addOption('memory-limit', null, InputOption::VALUE_REQUIRED, 'Memory limit (e.g., 128M)');
        $this->addOption('time-limit', null, InputOption::VALUE_REQUIRED, 'Time limit in seconds');
    }

    /**
     * @return list<int>
     */
    #[Override]
    public function getSubscribedSignals(): array
    {
        return [\SIGINT, \SIGTERM];
    }

    #[Override]
    public function handleSignal(int $signal, int|false $previousExitCode = 0): int|false
    {
        $this->shouldStop = true;

        return false;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var int|string $limit */
        $limit = $input->getOption('limit');
        $limit = (int) $limit;

        /** @var string|bool $watch */
        $watch = $input->getOption('watch');

        $this->outboxProcessor->process(limit: $limit);

        if ($watch === false) {
            return Command::SUCCESS;
        }

        $intervalMs = (int) ($watch ?: 100);
        $intervalUs = max($intervalMs, 10) * 1000;

        /** @var string|null $memoryLimitOption */
        $memoryLimitOption = $input->getOption('memory-limit');
        $memoryLimit = $memoryLimitOption !== null ? $this->parseMemoryLimit($memoryLimitOption) : null;

        /** @var string|null $timeLimitOption */
        $timeLimitOption = $input->getOption('time-limit');
        $timeLimit = $timeLimitOption !== null ? (int) $timeLimitOption : null;

        $startTime = time();

        while (!$this->shouldStop) {
            usleep($intervalUs);

            $this->outboxProcessor->process(limit: $limit);

            if ($memoryLimit !== null && memory_get_usage(true) >= $memoryLimit) {
                $output->writeln('Memory limit reached, stopping.');
                break;
            }

            if ($timeLimit !== null && (time() - $startTime) >= $timeLimit) {
                $output->writeln('Time limit reached, stopping.');
                break;
            }
        }

        return Command::SUCCESS;
    }

    private function parseMemoryLimit(string $limit): int
    {
        $limit = strtoupper(trim($limit));
        $value = (int) $limit;

        return match (true) {
            str_ends_with($limit, 'G') => $value * 1024 * 1024 * 1024,
            str_ends_with($limit, 'M') => $value * 1024 * 1024,
            str_ends_with($limit, 'K') => $value * 1024,
            default => $value,
        };
    }
}
