<?php

use Codeception\Configuration;
use Codeception\Test\Unit;
use Fkupper\Command\GenerateDynamicSnapshot;
use Symfony\Component\Console\Tester\CommandTester;

class GenerateDynamicSnapshotCommandTest extends Unit
{
    /** @var list<string> */
    private array $generatedFiles = [];

    protected function _after(): void
    {
        foreach ($this->generatedFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        $this->generatedFiles = [];

        $snapshotDir = Configuration::supportDir() . 'Snapshot';
        $unitDir = $snapshotDir . DIRECTORY_SEPARATOR . 'Unit';
        if (is_dir($unitDir)) {
            @rmdir($unitDir);
        }
        if (is_dir($snapshotDir)) {
            @rmdir($snapshotDir);
        }
    }

    public function testGetCommandNameAndDescription(): void
    {
        $command = new GenerateDynamicSnapshot(GenerateDynamicSnapshot::getCommandName());

        $this->assertSame('generate:dynamicsnapshot', GenerateDynamicSnapshot::getCommandName());
        $this->assertSame('Generates empty DynamicSnapshot class', $command->getDescription());
    }

    public function testExecuteCreatesSnapshotWithoutSuiteAndRejectsDuplicate(): void
    {
        $class = 'Gen' . bin2hex(random_bytes(4));
        $tester = $this->commandTester();

        $this->assertSame(0, $tester->execute(['suite' => $class]));

        $filename = $this->snapshotPath($class);
        $this->generatedFiles[] = $filename;
        $this->assertFileExists($filename);
        $contents = file_get_contents($filename);
        $this->assertStringContainsString("class {$class} extends DynamicSnapshot", $contents);
        $this->assertStringContainsString('fetchDynamicData', $contents);
        $this->assertStringContainsString('DynamicSnapshot was created', $tester->getDisplay());

        $this->assertSame(1, $tester->execute(['suite' => $class]));
        $this->assertStringContainsString('already exists', $tester->getDisplay());
    }

    public function testExecuteCreatesSnapshotForSuiteWithActor(): void
    {
        $class = 'Gen' . bin2hex(random_bytes(4));
        $tester = $this->commandTester();

        $this->assertSame(0, $tester->execute([
            'suite' => 'unit',
            'dynamicsnapshot' => $class,
        ]));

        $filename = $this->snapshotPath($class, 'Unit');
        $this->generatedFiles[] = $filename;
        $this->assertFileExists($filename);
        $contents = file_get_contents($filename);
        $this->assertStringContainsString("class {$class} extends DynamicSnapshot", $contents);
        $this->assertStringContainsString('UnitTester', $contents);
        $this->assertStringContainsString('function __construct', $contents);
    }

    private function commandTester(): CommandTester
    {
        return new CommandTester(
            new GenerateDynamicSnapshot(GenerateDynamicSnapshot::getCommandName())
        );
    }

    private function snapshotPath(string $class, ?string $suite = null): string
    {
        $path = Configuration::supportDir() . 'Snapshot' . DIRECTORY_SEPARATOR;
        if ($suite !== null) {
            $path .= $suite . DIRECTORY_SEPARATOR;
        }

        return $path . $class . '.php';
    }
}
