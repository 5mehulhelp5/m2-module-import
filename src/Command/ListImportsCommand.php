<?php

namespace Jh\Import\Command;

use Jh\Import\Config as ImportConfig;
use Jh\Import\Config\Data;
use Jh\Import\Locker\Locker;
use Magento\Cron\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class ListImportsCommand extends Command
{

    /**
     * @var Data
     */
    private $importConfig;

    /**
     * @var Config
     */
    private $cronConfig;

    /**
     * @var Locker
     */
    private $locker;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    public function __construct(Data $importConfig, Config $cronConfig, Locker $locker, ScopeConfigInterface $scopeConfig)
    {
        parent::__construct();
        $this->importConfig = $importConfig;
        $this->cronConfig = $cronConfig;
        $this->locker = $locker;
        $this->scopeConfig = $scopeConfig;
    }

    protected function configure()
    {
        $this->setName('jh-import:list-imports')
            ->setDescription('List all of the registered imports');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $input->setInteractive(true);

        $output->writeln('');

        $output->writeln('<comment>All imports registered with the system:</comment>');
        $output->writeln('');

        $jobs = $this->cronConfig->getJobs();

        (new Table($output))
            ->setHeaders(['Name', 'Type', 'Match Files', 'Incoming Directory', 'Cron Expr', 'Locked?'])
            ->setRows(array_map(function ($import) use ($jobs) {
                $config = $this->importConfig->getImportConfigByName($import);
                $schedule = $this->resolveSchedule($jobs, $config);

                return [
                    $config->getImportName(),
                    $config->getType(),
                    $config->get('match_files'),
                    $config->get('incoming_directory'),
                    $schedule,
                    $this->locker->locked($import) ? '<error>Yes</error>' : 'No'
                ];
            }, $this->importConfig->getAllImportNames()))
            ->render();

        $output->writeln('');

        return Cli::RETURN_SUCCESS;
    }

    private function resolveSchedule(array $jobs, ImportConfig $importConfig): string
    {
        $unknownSchedule = 'N/A';

        if (!$importConfig->hasCron() || !isset($jobs[$importConfig->getCronGroup()][$importConfig->getCron()])) {
            return $unknownSchedule;
        }

        $cronConfiguration = $jobs[$importConfig->getCronGroup()][$importConfig->getCron()];

        if (isset($cronConfiguration['schedule'])) {
            return $cronConfiguration['schedule'];
        }

        if (isset($cronConfiguration['config_path'])) {
            return (string) $this->scopeConfig->getValue($cronConfiguration['config_path']);
        }

        return $unknownSchedule;
    }
}
