<?php declare(strict_types=1);

namespace Mich418\ShopwareCustomFieldsSync\Tests\Sync;

use Mich418\ShopwareCustomFieldsSync\Sync\CustomFieldSetSynchronizer;
use Mich418\ShopwareCustomFieldsSync\Sync\DeterministicId;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

final class CustomFieldSetSynchronizerTest extends TestCase
{
    public function testSyncUpsertsSetRelationsFields(): void
    {
        $context = Context::createDefaultContext();

        $config = [
            [
                'name' => 'my_set',
                'config' => ['label' => ['en-GB' => 'My Set']],
                'relations' => [
                    ['entityName' => 'product'],
                ],
                'customFields' => [
                    [
                        'name' => 'field_one',
                        'type' => 'text',
                        'config' => ['label' => ['en-GB' => 'Field One']],
                    ],
                ],
            ],
        ];

        $setRepo = $this->createMock(EntityRepository::class);
        $fieldRepo = $this->createMock(EntityRepository::class);
        $relRepo = $this->createMock(EntityRepository::class);

        // Cleanup searches should return "nothing extra"
        $fieldRepo->method('search')->willReturn($this->mockSearchResult([]));
        $relRepo->method('search')->willReturn($this->mockSearchResult([]));

        $setRepo->expects(self::once())
            ->method('upsert')
            ->with(self::callback(function (array $payload) {
                self::assertCount(1, $payload);
                self::assertSame(DeterministicId::setId('my_set'), $payload[0]['id']);
                self::assertSame('my_set', $payload[0]['name']);
                self::assertSame(['label' => ['en-GB' => 'My Set']], $payload[0]['config']);
                return true;
            }), self::isInstanceOf(Context::class));

        $relRepo->expects(self::once())
            ->method('upsert')
            ->with(self::callback(function (array $payload) {
                self::assertCount(1, $payload);
                self::assertSame(DeterministicId::relationId('my_set', 'product'), $payload[0]['id']);
                self::assertSame(DeterministicId::setId('my_set'), $payload[0]['customFieldSetId']);
                self::assertSame('product', $payload[0]['entityName']);
                return true;
            }), self::isInstanceOf(Context::class));

        $fieldRepo->expects(self::once())
            ->method('upsert')
            ->with(self::callback(function (array $payload) {
                self::assertCount(1, $payload);
                self::assertSame('my_set_field_one', $payload[0]['name']);
                self::assertSame(DeterministicId::fieldId('my_set', 'my_set_field_one'), $payload[0]['id']);
                self::assertSame(DeterministicId::setId('my_set'), $payload[0]['customFieldSetId']);
                self::assertSame('text', $payload[0]['type']);
                self::assertSame(['label' => ['en-GB' => 'Field One']], $payload[0]['config']);
                return true;
            }), self::isInstanceOf(Context::class));

        // No deletes expected (no extras found)
        $fieldRepo->expects(self::never())->method('delete');
        $relRepo->expects(self::never())->method('delete');

        $sut = new CustomFieldSetSynchronizer($setRepo, $fieldRepo, $relRepo);
        $sut->sync($config, $context);
    }

    public function testSyncDeletesExtraFieldsAndRelations(): void
    {
        $context = Context::createDefaultContext();

        $config = [
            [
                'name' => 'my_set',
                'config' => [],
                'relations' => [
                    ['entityName' => 'product'],
                ],
                'customFields' => [
                    [
                        'name' => 'field_one',
                        'type' => 'text',
                        'config' => [],
                    ],
                ],
            ],
        ];

        $setRepo = $this->createMock(EntityRepository::class);
        $fieldRepo = $this->createMock(EntityRepository::class);
        $relRepo = $this->createMock(EntityRepository::class);

        // existing fields: expected + legacy
        $existingFields = [
            new class('my_set_field_one', 'id_expected') {
                public function __construct(private string $name, private string $id) {}
                public function getName(): string { return $this->name; }
                public function getId(): string { return $this->id; }
            },
            new class('my_set_legacy', 'id_legacy') {
                public function __construct(private string $name, private string $id) {}
                public function getName(): string { return $this->name; }
                public function getId(): string { return $this->id; }
            },
        ];

        // existing relations: expected + legacy
        $existingRelations = [
            new class('product', 'rel_expected') {
                public function __construct(private string $entityName, private string $id) {}
                public function getEntityName(): string { return $this->entityName; }
                public function getId(): string { return $this->id; }
            },
            new class('category', 'rel_legacy') {
                public function __construct(private string $entityName, private string $id) {}
                public function getEntityName(): string { return $this->entityName; }
                public function getId(): string { return $this->id; }
            },
        ];

        $fieldRepo->method('search')->willReturn($this->mockSearchResult($existingFields));
        $relRepo->method('search')->willReturn($this->mockSearchResult($existingRelations));

        // upserts still happen (source of truth)
        $setRepo->expects(self::once())->method('upsert');
        $fieldRepo->expects(self::once())->method('upsert');
        $relRepo->expects(self::once())->method('upsert');

        // deletes: should remove only legacy ones
        $fieldRepo->expects(self::once())
            ->method('delete')
            ->with(self::callback(function (array $payload) {
                self::assertSame([['id' => 'id_legacy']], $payload);
                return true;
            }), self::isInstanceOf(Context::class));

        $relRepo->expects(self::once())
            ->method('delete')
            ->with(self::callback(function (array $payload) {
                self::assertSame([['id' => 'rel_legacy']], $payload);
                return true;
            }), self::isInstanceOf(Context::class));

        $sut = new CustomFieldSetSynchronizer($setRepo, $fieldRepo, $relRepo);
        $sut->sync($config, $context);
    }

    public function testRemoveDeletesSetsByName(): void
    {
        $context = Context::createDefaultContext();

        $config = [
            ['name' => 'set_a'],
            ['name' => 'set_b'],
        ];

        $setRepo = $this->createMock(EntityRepository::class);
        $fieldRepo = $this->createMock(EntityRepository::class);
        $relRepo = $this->createMock(EntityRepository::class);

        // Search called twice: once per set name, returns entity with id
        $setRepo->expects(self::exactly(2))
            ->method('search')
            ->willReturnOnConsecutiveCalls(
                $this->mockSearchResult([
                    new class('set_a_id') {
                        public function __construct(private string $id) {}
                        public function getId(): string { return $this->id; }
                    }
                ]),
                $this->mockSearchResult([
                    new class('set_b_id') {
                        public function __construct(private string $id) {}
                        public function getId(): string { return $this->id; }
                    }
                ]),
            );

        $setRepo->expects(self::once())
            ->method('delete')
            ->with(self::callback(function (array $payload) {
                self::assertSame([['id' => 'set_a_id'], ['id' => 'set_b_id']], $payload);
                return true;
            }), self::isInstanceOf(Context::class));

        // remove should not touch fields/relations directly
        $fieldRepo->expects(self::never())->method('delete');
        $relRepo->expects(self::never())->method('delete');

        $sut = new CustomFieldSetSynchronizer($setRepo, $fieldRepo, $relRepo);
        $sut->remove($config, $context);
    }

    private function mockSearchResult(array $entities): EntitySearchResult
    {
        $result = $this->createMock(EntitySearchResult::class);
        $result->method('getEntities')->willReturn($entities);
        return $result;
    }
}
