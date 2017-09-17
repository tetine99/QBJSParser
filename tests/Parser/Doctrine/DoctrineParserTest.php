<?php

namespace FL\QBJSParser\Tests\Parser\Doctrine;

use FL\QBJSParser\Exception\Parser\Doctrine\FieldMappingException;
use FL\QBJSParser\Exception\Parser\Doctrine\InvalidClassNameException;
use FL\QBJSParser\Model\Rule;
use FL\QBJSParser\Model\RuleGroup;
use FL\QBJSParser\Model\RuleGroupInterface;
use FL\QBJSParser\Parser\Doctrine\DoctrineParser;
use FL\QBJSParser\Tests\Util\Doctrine\Mock\DoctrineParser\MockBadEntity2DoctrineParser;
use FL\QBJSParser\Tests\Util\Doctrine\Mock\DoctrineParser\MockBadEntityDoctrineParser;
use FL\QBJSParser\Tests\Util\Doctrine\Mock\DoctrineParser\MockEntityDoctrineParser;
use FL\QBJSParser\Tests\Util\Doctrine\Mock\DoctrineParser\MockEntityWithAssociationDoctrineParser;
use FL\QBJSParser\Tests\Util\Doctrine\Mock\DoctrineParser\MockEntityWithEmbeddableDoctrineParser;
use FL\QBJSParser\Tests\Util\Doctrine\Mock\Entity\MockEntity;
use FL\QBJSParser\Tests\Util\Doctrine\Test\DoctrineParserTestCase;
use PHPUnit\Framework\TestCase;

class DoctrineParserTest extends TestCase
{
    public function testMockBadEntity()
    {
        self::expectException(FieldMappingException::class);

        new MockBadEntityDoctrineParser();
    }

    public function testMockBadEntity2()
    {
        self::expectException(FieldMappingException::class);

        new MockBadEntity2DoctrineParser();
    }

    public function testNonExistentClass()
    {
        self::expectException(InvalidClassNameException::class);

        new DoctrineParser('This_Really_Long_Class_Name_With_Invalid_Characters_@#_IS_NOT_A_CLASS', [], []);
    }

    /**
     * @dataProvider entityParseCasesProvider
     * @dataProvider associationParseCasesProvider
     * @dataProvider embeddableParseCasesProvider
     *
     * @param DoctrineParserTestCase $testCase
     */
    public function testParserReturnsExpectedDqlAndParameters(DoctrineParserTestCase $testCase)
    {
        $parsed = $testCase->getDoctrineParser()->parse($testCase->getRuleGroup(), $testCase->getSortColumns());

        $dqlString = $parsed->getQueryString();
        $parameters = $parsed->getParameters();

        self::assertEquals($testCase->getExpectedDqlString(), $dqlString);
        self::assertEquals($testCase->getExpectedParameters(), $parameters);
    }

    public function entityParseCasesProvider(): array
    {
        $testCases = [];

        $ruleGroup = (new RuleGroup(RuleGroupInterface::MODE_AND))
            ->addRule(new Rule('rule_id', 'price', 'double', 'is_not_null', null))
        ;
        $testCases[] = [new DoctrineParserTestCase(
            new MockEntityDoctrineParser(),
            $ruleGroup,
            [
                'price' => 'ASC',
            ],
            'SELECT object FROM '.MockEntity::class.' object WHERE ( object.price IS NOT NULL ) '.
            'ORDER BY object.price ASC',
            []
        )];

        // no where clause
        $ruleGroup = new RuleGroup(RuleGroupInterface::MODE_AND);
        $testCases[] = [new DoctrineParserTestCase(
            new MockEntityDoctrineParser(),
            $ruleGroup,
            [
                'date' => 'ASC',
            ],
            'SELECT object FROM '.MockEntity::class.' object '.
            'ORDER BY object.date ASC',
            []
        )];

        $ruleGroup = (new RuleGroup(RuleGroupInterface::MODE_OR))
            ->addRule(new Rule('rule_id', 'price', 'double', 'is_not_null', null))
            ->addRule(new Rule('rule_id', 'name', 'string', 'equal', 'hello'))
            ->addRule(new Rule('rule_id', 'name', 'string', 'contains', 'world'))
            ->addRule(new Rule('rule_id', 'name', 'string', 'not_contains', 'world'))
            ->addRule(new Rule('rule_id', 'name', 'string', 'begins_with', 'world'))
            ->addRule(new Rule('rule_id', 'name', 'string', 'ends_with', 'world'))
            ->addRule(new Rule('rule_id', 'name', 'string', 'not_begins_with', 'world'))
            ->addRule(new Rule('rule_id', 'name', 'string', 'not_ends_with', 'world'))
            ->addRule(new Rule('rule_id', 'name', 'string', 'is_empty', null))
            ->addRule(new Rule('rule_id', 'name', 'string', 'is_not_empty', null))
        ;
        $testCases[] = [new DoctrineParserTestCase(
            new MockEntityDoctrineParser(),
            $ruleGroup,
            [
                'price' => 'ASC',
                'name' => 'DESC',
            ],
            sprintf(
                'SELECT object FROM %s object '.
                'WHERE ( object.price IS NOT NULL OR object.name = ?0'.
                ' OR object.name LIKE ?1 OR object.name NOT LIKE ?2'.
                ' OR object.name LIKE ?3 OR object.name LIKE ?4'.
                ' OR object.name NOT LIKE ?5 OR object.name NOT LIKE ?6'.
                ' OR object.name = \'\' OR object.name != \'\' )'.
                ' ORDER BY object.price ASC, object.name DESC',
                MockEntity::class
            ),
            [
                'hello',
                '%world%',
                '%world%',
                'world%',
                '%world',
                'world%',
                '%world',
            ]
        )];

        $ruleGroup = (new RuleGroup(RuleGroupInterface::MODE_AND))
            ->addRule(new Rule('rule_id', 'price', 'double', 'is_not_null', null))
            ->addRule(new Rule('rule_id', 'name', 'string', 'equal', 'hello'))
            ->addRuleGroup(
                (new RuleGroup(RuleGroupInterface::MODE_OR))
                    ->addRule(new Rule('rule_id', 'price', 'double', 'greater', 0.3))
                    ->addRule(new Rule('rule_id', 'price', 'double', 'less_or_equal', 22.0))
            )
        ;

        $testCases[] = [new DoctrineParserTestCase(
            new MockEntityDoctrineParser(),
            $ruleGroup,
            [
                'name' => 'DESC',
                'price' => 'ASC',
            ],
            sprintf(
                'SELECT object FROM %s object '.
                'WHERE ( object.price IS NOT NULL AND object.name = ?0 '.
                'AND ( object.price > ?1 OR object.price <= ?2 ) ) '.
                'ORDER BY object.name DESC, object.price ASC',
                MockEntity::class
            ),
            ['hello', 0.3, 22.0]
        )];

        return $testCases;
    }

    public function associationParseCasesProvider(): array
    {
        $testCases = [];

        $ruleGroup = (new RuleGroup(RuleGroupInterface::MODE_AND))
            ->addRule(new Rule('rule_id', 'price', 'double', 'is_not_null', null))
            ->addRule(new Rule('rule_id', 'associationEntity.id', 'string', 'equal', 'hello'))
        ;

        $testCases[] = [new DoctrineParserTestCase(
            new MockEntityWithAssociationDoctrineParser(),
            $ruleGroup,
            [
                'name' => 'DESC',
                'associationEntity.id' => 'ASC',
            ],
            sprintf(
                'SELECT object, object_associationEntity FROM %s object '.
                'LEFT JOIN object.associationEntity object_associationEntity '.
                'WHERE ( object.price IS NOT NULL AND object_associationEntity.id = ?0 ) '.
                'ORDER BY object.name DESC, object_associationEntity.id ASC',
                MockEntity::class
            ),
            ['hello']
        )];

        return $testCases;
    }

    public function embeddableParseCasesProvider(): array
    {
        $testCases = [];

        $dateA = new \DateTimeImmutable('now - 2 days');
        $dateB = new \DateTimeImmutable('now + 2 days');
        $ruleGroup = (new RuleGroup(RuleGroupInterface::MODE_AND))
            ->addRule(new Rule('rule_id', 'price', 'double', 'is_not_null', null))
            ->addRule(new Rule('rule_id', 'associationEntity.id', 'string', 'equal', 'hello'))
            ->addRule(new Rule('rule_id', 'embeddable.startDate', 'date', 'equal', $dateA))
            ->addRule(new Rule('rule_id', 'associationEntity.embeddable.startDate', 'date', 'equal', $dateA))
            ->addRule(new Rule('rule_id', 'associationEntity.embeddable.endDate', 'date', 'equal', $dateB))
            ->addRule(new Rule('rule_id', 'associationEntity.associationEntity.embeddable.startDate', 'date', 'equal', $dateA))
            ->addRule(new Rule('rule_id', 'embeddable.embeddableInsideEmbeddable.code', 'string', 'equal', 'goodbye'))
            ->addRule(new Rule('rule_id', 'associationEntity.embeddable.embeddableInsideEmbeddable.code', 'string', 'equal', 'cool'))
        ;
        $testCases[] = [new DoctrineParserTestCase(
            new MockEntityWithEmbeddableDoctrineParser(),
            $ruleGroup,
            [
                'name' => 'DESC',
                'associationEntity.id' => 'ASC',
                'associationEntity.embeddable.startDate' => 'ASC',
                'associationEntity.associationEntity.embeddable.startDate' => 'DESC',
                'embeddable.embeddableInsideEmbeddable.code' => 'ASC',
                'associationEntity.embeddable.embeddableInsideEmbeddable.code' => 'DESC',
            ],
            sprintf(
                'SELECT object, object_associationEntity FROM %s object '.
                'LEFT JOIN object.associationEntity object_associationEntity '.
                'WHERE ( object.price IS NOT NULL AND object_associationEntity.id = ?0 '.
                'AND object.embeddable.startDate = ?1 AND object_associationEntity.embeddable.startDate = ?2 '.
                'AND object_associationEntity.embeddable.endDate = ?3 '.
                'AND object_associationEntity_associationEntity.embeddable.startDate = ?4 '.
                'AND object.embeddable.embeddableInsideEmbeddable.code = ?5 '.
                'AND object_associationEntity.embeddable.embeddableInsideEmbeddable.code = ?6 ) '.
                'ORDER BY object.name DESC, object_associationEntity.id ASC, '.
                'object_associationEntity.embeddable.startDate ASC, '.
                'object_associationEntity_associationEntity.embeddable.startDate DESC, '.
                'object.embeddable.embeddableInsideEmbeddable.code ASC, '.
                'object_associationEntity.embeddable.embeddableInsideEmbeddable.code DESC',
                MockEntity::class
            ),
            ['hello', $dateA, $dateA, $dateB, $dateA, 'goodbye', 'cool']
        )];

        return $testCases;
    }
}
