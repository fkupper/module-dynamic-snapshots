<?php

namespace Fkupper\Command;

use Codeception\Command\Shared\ConfigTrait;
use Codeception\Command\Shared\FileSystemTrait;
use Codeception\Configuration;
use Codeception\CustomCommandInterface;
use Fkupper\Lib\Generator\DynamicSnapshot as DynamicSnapshotGenerator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Generates DynamicSnapshot.
 * DynamicSnapshot can be used to test dynamical data.
 * If suite name is provided, an actor class will be included into placeholder
 *
 * * `codecept g:dynamicsnapshot UserEmails`
 * * `codecept g:dynamicsnapshot Products`
 * * `codecept g:dynamicsnapshot acceptance UserEmails`
 */
class GenerateDynamicSnapshot extends Command implements CustomCommandInterface
{
    use FileSystemTrait;
    use ConfigTrait;

    public static function getCommandName(): string
    {
        return 'generate:dynamicsnapshot';
    }

    protected function configure()
    {
        $this->setDefinition([
            new InputArgument('suite', InputArgument::REQUIRED, 'Suite name or snapshot name)'),
            new InputArgument('dynamicsnapshot', InputArgument::OPTIONAL, 'Name of snapshot'),
        ]);
        parent::configure();
    }

    public function getDescription(): string
    {
        return 'Generates empty DynamicSnapshot class';
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $suite = (string)$input->getArgument('suite');
        $class = (string)$input->getArgument('dynamicsnapshot');

        if (!$class) {
            $class = $suite;
            $suite = null;
        }

        $conf = $suite
            ? $this->getSuiteConfig($suite)
            : $this->getGlobalConfig();

        if ($suite) {
            $suite = DIRECTORY_SEPARATOR . ucfirst($suite);
        } else {
            $suite = '';
        }

        $path = $this->createDirectoryFor(Configuration::supportDir() . 'Snapshot' . $suite, $class);

        $filename = $path . $this->getShortClassName($class) . '.php';

        $output->writeln($filename);

        $gen = new DynamicSnapshotGenerator($conf, ucfirst($suite) . '\\' . $class);
        $res = $this->createFile($filename, $gen->produce());

        if (!$res) {
            $output->writeln("<error>DynamicSnapshot $filename already exists</error>");
            return 1;
        }
        $output->writeln("<info>DynamicSnapshot was created in $filename</info>");
        return 0;
    }
}
