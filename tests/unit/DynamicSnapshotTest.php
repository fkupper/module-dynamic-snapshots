<?php

use Codeception\Test\Unit;
use Codeception\DynamicSnapshot;

class DynamicSnapshotTest extends Unit
{
    /**
     * @test
     * @covers \Codeception\DynamicSnapshot::setWrappers
     * @covers \Codeception\DynamicSnapshot::getLeftWrapper
     * @covers \Codeception\DynamicSnapshot::getRightWrapper
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
     * @covers \Codeception\DynamicSnapshot::getLeftWrapper
     * @covers \Codeception\DynamicSnapshot::getRightWrapper
     */
    public function haveDefaultWrappers()
    {
        $mock = Mockery::mock(DynamicSnapshot::class)->makePartial();
        $this->assertEquals('[', $mock->getLeftWrapper());
        $this->assertEquals(']', $mock->getRightWrapper());
    }

    /**
     * @test
     * @covers \Codeception\DynamicSnapshot::shouldAllowTrailingSpaces
     * @covers \Codeception\DynamicSnapshot::getAllowTrailingSpaces
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
     * @covers \Codeception\DynamicSnapshot::shouldAllowSpaceSequences
     * @covers \Codeception\DynamicSnapshot::getAllowSpaceSequences
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
     * @covers \Codeception\DynamicSnapshot::wrapAndQuote
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
     * @covers \Codeception\DynamicSnapshot::quoteAndWrap
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
     * @covers \Codeception\DynamicSnapshot::cleanContent
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
     * @covers \Codeception\DynamicSnapshot::cleanContent
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
     * @covers \Codeception\DynamicSnapshot::cleanContent
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
}
