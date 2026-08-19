<?php

use Codeception\Attribute\DataProvider;
use Codeception\Exception\ContentNotFound;
use Codeception\Test\Unit;
use Fkupper\Codeception\DynamicSnapshot;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\ExpectationFailedException;

class DynamicSnapshotTest extends Unit
{
    /** @var list<string> */
    private array $snapshotFiles = [];

    protected function _after(): void
    {
        foreach ($this->snapshotFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        $this->snapshotFiles = [];
    }

    public function testCanSetWrappers(): void
    {
        $mock = Mockery::mock(DynamicSnapshot::class)->makePartial();
        $mock->setWrappers('{', '}');
        $this->assertEquals('{', $mock->getLeftWrapper());
        $this->assertEquals('}', $mock->getRightWrapper());
    }

    public function testHaveDefaultWrappers(): void
    {
        $mock = Mockery::mock(DynamicSnapshot::class)->makePartial();
        $this->assertEquals('[', $mock->getLeftWrapper());
        $this->assertEquals(']', $mock->getRightWrapper());
    }

    public function testCanAllowTrailingSpaces(): void
    {
        $mock = Mockery::mock(DynamicSnapshot::class)->makePartial();
        $this->assertFalse($mock->getAllowTrailingSpaces());
        $mock->shouldAllowTrailingSpaces();
        $this->assertTrue($mock->getAllowTrailingSpaces());
    }

    public function testCanAllowSpaceSequences(): void
    {
        $mock = Mockery::mock(DynamicSnapshot::class)->makePartial();
        $this->assertFalse($mock->getAllowSpaceSequences());
        $mock->shouldAllowSpaceSequences();
        $this->assertTrue($mock->getAllowSpaceSequences());
    }

    public function testCanWrapAndQuote(): void
    {
        $mock = Mockery::mock(DynamicSnapshot::class)->makePartial();
        $value = '/\?^$';

        $this->assertEquals(
            '\[\/\\\\\\?\^\$\]',
            $mock->wrapAndQuote($value)
        );
    }

    public function testCanQuoteAndWrap(): void
    {
        $mock = Mockery::mock(DynamicSnapshot::class)->makePartial();
        $value = '/\?^$';

        $this->assertEquals(
            '[\/\\\\\\?\^\$]',
            $mock->quoteAndWrap($value)
        );
    }

    public function testCanCleanContentSpaceSequence(): void
    {
        $value = '   foo   bar   baz        asd    ';
        $mock = Mockery::mock(DynamicSnapshot::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial()
            ->shouldReceive('getAllowSpaceSequences')
            ->andReturn(false)
            ->once()
            ->shouldReceive('getAllowTrailingSpaces')
            ->andReturn(true)
            ->once()
            ->getMock();
        $this->assertEquals(
            ' foo bar baz asd ',
            $mock->cleanContent($value)
        );
    }

    public function testCanCleanContentTrailingSpaces(): void
    {
        $value = '   foo   bar   baz        asd    ';
        $mock = Mockery::mock(DynamicSnapshot::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial()
            ->shouldReceive('getAllowSpaceSequences')
            ->andReturn(true)
            ->once()
            ->shouldReceive('getAllowTrailingSpaces')
            ->andReturn(false)
            ->once()
            ->getMock();
        $this->assertEquals(
            'foo   bar   baz        asd',
            $mock->cleanContent($value)
        );
    }

    public function testCanCleanContent(): void
    {
        $value = '   foo   bar   baz        asd    ';
        $mock = Mockery::mock(DynamicSnapshot::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial()
            ->shouldReceive('getAllowSpaceSequences')
            ->andReturn(false)
            ->once()
            ->shouldReceive('getAllowTrailingSpaces')
            ->andReturn(false)
            ->once()
            ->getMock();
        $this->assertEquals(
            'foo bar baz asd',
            $mock->cleanContent($value)
        );
    }

    #[DataProvider('provideInvalidSubstitutions')]
    public function testItWillNotAllowUnsupportedSubstitutions(array $substitutions): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Substitutions can only be string values or values that can be casted to string. ' .
            'You provided substitution `element` of type ' . getType($substitutions['element'])
        );
        $mock = Mockery::mock(DynamicSnapshot::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();

        $mock->setSubstitutions($substitutions);
    }

    public static function provideInvalidSubstitutions(): array
    {
        return [
            'object_with_no_to_string_method' => [[
                'element' => new stdClass(),
            ]],
            'nested_array' => [[
                'element' => [],
            ]],
        ];
    }

    #[DataProvider('provideValidSubstitutions')]
    public function testItWillAllowSupportedSubstitutions(array $substitutions): void
    {
        $mock = Mockery::mock(DynamicSnapshot::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();

        $mock->setSubstitutions($substitutions);
    }

    public static function provideValidSubstitutions(): array
    {
        return [
            'string' => [[
                'element' => 'John Snow',
            ]],
            'int' => [[
                'element' => 2,
            ]],
            'object_with_to_string' => [[
                'element' => new StringableSubstitutionStub(),
            ]],
        ];
    }

    #[DataProvider('provideInvalidSubstitutions')]
    public function testItWillNotAllowUnsupportedStrictSubstitutions(array $substitutions): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Strict substitutions can only be string values or values that can be casted to string. ' .
            'You provided substitution `element` of type ' . getType($substitutions['element'])
        );
        $mock = Mockery::mock(DynamicSnapshot::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();

        $mock->setStrictSubstitutions($substitutions);
    }

    #[DataProvider('provideValidSubstitutions')]
    public function testItWillAllowSupportedStrictSubstitutions(array $substitutions): void
    {
        $mock = Mockery::mock(DynamicSnapshot::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();

        $mock->setStrictSubstitutions($substitutions);
    }

    public function testItCanGetSubstitutionsOutput(): void
    {
        $mock = Mockery::mock(DynamicSnapshot::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();

        $substitutions = [
            'foo' => 'bar',
            'baz' => 'asd',
            'int' => 2,
            'float' => 2.5,
        ];
        $mock->setSubstitutions($substitutions);
        $actualOutput = $mock->getSubstitutionsOutput();
        $expectedOutput = PHP_EOL . PHP_EOL . 'Substitutions:' . PHP_EOL . print_r($substitutions, true) . PHP_EOL;

        $this->assertSame(
            $expectedOutput,
            $actualOutput
        );
    }

    public function testItCanGetStrictSubstitutionsOutput(): void
    {
        $mock = Mockery::mock(DynamicSnapshot::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();

        $substitutions = [
            'foo' => 'bar',
            'baz' => 'asd',
            'int' => 2,
            'float' => 2.5,
        ];
        $mock->setStrictSubstitutions($substitutions);
        $actualOutput = $mock->getStrictSubstitutionsOutput();
        $expectedOutput = PHP_EOL . PHP_EOL . 'Strict substitutions:' . PHP_EOL . print_r($substitutions, true) . PHP_EOL;

        $this->assertSame(
            $expectedOutput,
            $actualOutput
        );
    }

    public function testItCanGetSubstitutionKey(): void
    {
        $mock = Mockery::mock(DynamicSnapshot::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();

        $actualKey = $mock->getSubstitutionKey('foo', false);
        $this->assertSame(
            'snapshot_foo',
            $actualKey,
        );

        $actualStrictKey = $mock->getSubstitutionKey('foo', true);
        $this->assertSame(
            'snapshot_strict_foo',
            $actualStrictKey,
        );
    }

    public function testItCanReplaceRealValueWithPlaceholderWithoutBoundaries(): void
    {
        $mock = Mockery::mock(DynamicSnapshot::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();

        $reflectionClass = new ReflectionClass($mock);
        $property = $reflectionClass->getProperty('dataSet');
        $property->setAccessible(true);
        $property->setValue($mock, 'asdfoobarfooqwe');

        $mock->replaceRealValueWithPlaceholder('foo', 'placeholder_for_foo');

        $expectedDataSet = 'asd[placeholder_for_foo]bar[placeholder_for_foo]qwe';
        $actualDataSet = $property->getValue($mock);

        $this->assertSame(
            $expectedDataSet,
            $actualDataSet
        );
    }

    public function testItCanReplaceRealValueWithPlaceholderWithBoundaries(): void
    {
        $mock = Mockery::mock(DynamicSnapshot::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();

        $reflectionClass = new ReflectionClass($mock);
        $property = $reflectionClass->getProperty('dataSet');
        $property->setAccessible(true);
        $property->setValue($mock, 'asd=foo"bar foo&qwe foo');

        $mock->replaceRealValueWithPlaceholder('foo', 'placeholder_for_foo', true);

        $expectedDataSet = 'asd=[placeholder_for_foo]"bar [placeholder_for_foo]&qwe [placeholder_for_foo]';
        $actualDataSet = $property->getValue($mock);

        $this->assertSame(
            $expectedDataSet,
            $actualDataSet
        );
    }

    public function testItStringifiesStringableObjectSubstitutions(): void
    {
        $mock = Mockery::mock(DynamicSnapshot::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();

        $mock->setSubstitutions([
            'element' => new StringableSubstitutionStub(),
        ]);
        $mock->setStrictSubstitutions([
            'element' => new StringableSubstitutionStub(),
        ]);

        $reflectionClass = new ReflectionClass($mock);
        $substitutions = $reflectionClass->getProperty('substitutions');
        $substitutions->setAccessible(true);
        $strictSubstitutions = $reflectionClass->getProperty('strictSubstitutions');
        $strictSubstitutions->setAccessible(true);

        $this->assertSame(
            ['snapshot_element' => 'John Snow'],
            $substitutions->getValue($mock)
        );
        $this->assertSame(
            ['snapshot_strict_element' => 'John Snow'],
            $strictSubstitutions->getValue($mock)
        );
    }

    public function testCanRemoveIgnoredLines(): void
    {
        $mock = Mockery::mock(DynamicSnapshot::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();

        $mock->setIgnoredLinesPatterns(['/^ignore this.*$/m']);

        $this->assertSame(
            "keep\n\nkeep2\n",
            $mock->removeIgnoredLines("keep\nignore this line\nkeep2\n")
        );
    }

    public function testEmptyWrappersAreNotAllowed(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Wrappers cannot be empty strings.');

        $mock = Mockery::mock(DynamicSnapshot::class)->makePartial();
        $mock->setWrappers('', ']');
    }

    public function testEmptySubstitutionsFailWhenSaving(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Error while saving snapshot: one or more substitutions is empty.');

        $mock = Mockery::mock(DynamicSnapshot::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();
        $mock->setSubstitutions(['foo' => '']);
        $mock->replaceRealValuesWithPlaceholders();
    }

    public function testEmptyStrictSubstitutionsFailWhenSaving(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Error while saving snapshot: one or more strict substitutions is empty.');

        $mock = Mockery::mock(DynamicSnapshot::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();
        $mock->setStrictSubstitutions(['foo' => '']);
        $mock->replaceRealValuesWithStrictPlaceholders();
    }

    public function testFetchDataFailsWhenDynamicDataIsEmpty(): void
    {
        $this->expectException(ContentNotFound::class);
        $this->expectExceptionMessage('Fetched dynamic snapshot is empty.');

        $snapshot = $this->makeSnapshot('');
        $snapshot->assert();
    }

    public function testAssertRoundTripReplacesAndRestoresSubstitutions(): void
    {
        $snapshot = $this->makeSnapshot('user:Alice id:42 ok');
        $snapshot->setSubstitutions(['user' => 'Alice']);
        $snapshot->setStrictSubstitutions(['id' => '42']);

        $snapshot->assert();

        $this->assertSame(
            'user:[snapshot_user] id:[snapshot_strict_id] ok',
            file_get_contents($snapshot->snapshotFile())
        );

        $snapshot->setSubstitutions(['user' => 'Bob']);
        $snapshot->setStrictSubstitutions(['id' => '99']);
        $snapshot->fetched = 'user:Bob id:99 ok';
        $snapshot->assert();
    }

    public function testAssertIgnoresMatchingLinesOnSaveAndCompare(): void
    {
        $snapshot = $this->makeSnapshot("keep\nnoise:111");
        $snapshot->setIgnoredLinesPatterns(['/^noise:.*$/m']);

        $snapshot->assert();
        $this->assertSame('keep', file_get_contents($snapshot->snapshotFile()));

        $snapshot->fetched = "keep\nnoise:222";
        $snapshot->assert();
    }

    public function testAssertFailureIncludesSubstitutionOutputWhenShowingDiff(): void
    {
        $snapshot = $this->makeSnapshot('user:Alice id:42 ok');
        $snapshot->setSubstitutions(['user' => 'Alice']);
        $snapshot->setStrictSubstitutions(['id' => '42']);
        $snapshot->assert();

        $snapshot->fetched = 'user:Alice id:42 changed';

        try {
            $snapshot->assert();
            $this->fail('Expected snapshot assertion to fail');
        } catch (ExpectationFailedException $exception) {
            $message = $exception->getMessage();
            $this->assertStringContainsString("Snapshot doesn't match real data", $message);
            $this->assertStringContainsString('Substitutions:', $message);
            $this->assertStringContainsString('[user] => Alice', $message);
            $this->assertStringContainsString('Strict substitutions:', $message);
            $this->assertStringContainsString('[id] => 42', $message);
        }
    }

    private function makeSnapshot(string $fetched = ''): RecordingDynamicSnapshot
    {
        $dataDir = codecept_data_dir();
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0777, true);
        }

        $snapshot = new RecordingDynamicSnapshot(
            'dynamic-snapshot-' . bin2hex(random_bytes(8)) . '.txt'
        );
        $snapshot->fetched = $fetched;
        $this->snapshotFiles[] = $snapshot->snapshotFile();

        return $snapshot;
    }
}

class StringableSubstitutionStub
{
    public function __toString(): string
    {
        return 'John Snow';
    }
}

class RecordingDynamicSnapshot extends DynamicSnapshot
{
    public string $fetched = '';

    public function __construct(string $fileName)
    {
        $this->fileName = $fileName;
        $this->shouldSaveAsJson(false);
        $this->setSnapshotFileExtension('txt');
        $this->shouldShowDiffOnFail();
        $this->shouldRefreshSnapshot(false);
    }

    protected function fetchDynamicData(): string
    {
        return $this->fetched;
    }

    public function snapshotFile(): string
    {
        return $this->getFileName();
    }
}
