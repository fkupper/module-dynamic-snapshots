<?php

use Codeception\Attribute\DataProvider;
use Codeception\Test\Unit;
use Fkupper\Codeception\DynamicSnapshot;

class DynamicSnapshotTest extends Unit
{
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
}
