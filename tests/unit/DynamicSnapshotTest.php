<?php

use Codeception\Test\Unit;
use Fkupper\Codeception\DynamicSnapshot;
use PHPUnit\Framework\ExpectationFailedException;

class DynamicSnapshotTest extends Unit
{
    /**
     * @test
     * @covers \Fkupper\Codeception\DynamicSnapshot::setWrappers
     * @covers \Fkupper\Codeception\DynamicSnapshot::getLeftWrapper
     * @covers \Fkupper\Codeception\DynamicSnapshot::getRightWrapper
     */
    public function canSetWrappers()
    {
        $mock = Mockery::mock(DynamicSnapshot::class)->makePartial();
        $mock->setWrappers('{', '}');
        $this->assertEquals('{', $mock->getLeftWrapper());
        $this->assertEquals('}', $mock->getRightWrapper());
    }

    /**
     * @test
     * @covers \Fkupper\Codeception\DynamicSnapshot::getLeftWrapper
     * @covers \Fkupper\Codeception\DynamicSnapshot::getRightWrapper
     */
    public function haveDefaultWrappers()
    {
        $mock = Mockery::mock(DynamicSnapshot::class)->makePartial();
        $this->assertEquals('[', $mock->getLeftWrapper());
        $this->assertEquals(']', $mock->getRightWrapper());
    }

    /**
     * @test
     * @covers \Fkupper\Codeception\DynamicSnapshot::shouldAllowTrailingSpaces
     * @covers \Fkupper\Codeception\DynamicSnapshot::getAllowTrailingSpaces
     */
    public function canAllowTrailingSpaces()
    {
        $mock = Mockery::mock(DynamicSnapshot::class)->makePartial();
        $this->assertFalse($mock->getAllowTrailingSpaces());
        $mock->shouldAllowTrailingSpaces();
        $this->assertTrue($mock->getAllowTrailingSpaces());
    }

    /**
     * @test
     * @covers \Fkupper\Codeception\DynamicSnapshot::shouldAllowSpaceSequences
     * @covers \Fkupper\Codeception\DynamicSnapshot::getAllowSpaceSequences
     */
    public function canAllowSpaceSequences()
    {
        $mock = Mockery::mock(DynamicSnapshot::class)->makePartial();
        $this->assertFalse($mock->getAllowSpaceSequences());
        $mock->shouldAllowSpaceSequences();
        $this->assertTrue($mock->getAllowSpaceSequences());
    }

    /**
     * @test
     * @covers \Fkupper\Codeception\DynamicSnapshot::wrapAndQuote
     */
    public function canWrapAndQuote()
    {
        $mock = Mockery::mock(DynamicSnapshot::class)->makePartial();
        $value = '/\?^$';

        $this->assertEquals(
            '\[\/\\\\\\?\^\$\]',
            $mock->wrapAndQuote($value)
        );
    }

    /**
     * @test
     * @covers \Fkupper\Codeception\DynamicSnapshot::quoteAndWrap
     */
    public function canQuoteAndWrap()
    {
        $mock = Mockery::mock(DynamicSnapshot::class)->makePartial();
        $value = '/\?^$';

        $this->assertEquals(
            '[\/\\\\\\?\^\$]',
            $mock->quoteAndWrap($value)
        );
    }

    /**
     * @test
     * @covers \Fkupper\Codeception\DynamicSnapshot::cleanContent
     */
    public function canCleanContentSpaceSequence()
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

    /**
     * @test
     * @covers \Fkupper\Codeception\DynamicSnapshot::cleanContent
     */
    public function canCleanContentTrailingSpaces()
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

    /**
     * @test
     * @covers \Fkupper\Codeception\DynamicSnapshot::cleanContent
     */
    public function canCleanContent()
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

    /**
     * @test
     * @covers \Fkupper\Codeception\DynamicSnapshot::setSubstitutions
     * @dataProvider provideInvalidSubstitutions
     */
    public function itWillNotAllowUnsupportedSubstitutions(array $substitutions)
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

    public function provideInvalidSubstitutions()
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

    /**
     * @test
     * @covers \Fkupper\Codeception\DynamicSnapshot::setSubstitutions
     * @dataProvider provideValidSubstitutions
     */
    public function itWillAllowSupportedSubstitutions(array $substitutions)
    {
        $mock = Mockery::mock(DynamicSnapshot::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();

        $mock->setSubstitutions($substitutions);
    }

    public function provideValidSubstitutions()
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

    /**
     * @test
     * @covers \Fkupper\Codeception\DynamicSnapshot::getSubstitutionsOutput
     */
    public function itCanGetSubstitutionsOutput()
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
        $expectedOutput = "\n\nSubstitutions:\n" . print_r($substitutions, true) . "\n";

        $this->assertSame(
            $expectedOutput,
            $actualOutput
        );
    }
}
