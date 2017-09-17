<?php

namespace FL\QBJSParser\Tests\Model;

use FL\QBJSParser\Exception\Model\RuleGroupConstructionException;
use FL\QBJSParser\Model\RuleGroup;
use FL\QBJSParser\Model\RuleGroupInterface;
use PHPUnit\Framework\TestCase;

class RuleGroupTest extends TestCase
{
    public function testModeOr()
    {
        self::assertInstanceOf(RuleGroupInterface::class, new RuleGroup(RuleGroup::MODE_OR));
    }

    public function testModeAnd()
    {
        self::assertInstanceOf(RuleGroupInterface::class, new RuleGroup(RuleGroup::MODE_AND));
    }

    public function testInvalidMode()
    {
        self::expectException(RuleGroupConstructionException::class);

        new RuleGroup(1000);
    }
}
