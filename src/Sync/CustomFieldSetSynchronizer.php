<?php declare(strict_types=1);

namespace Mich418\ShopwareCustomFieldsSync\Sync;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

final class CustomFieldSetSynchronizer implements CustomFieldSetSynchronizerInterface
{
    public function __construct(
        private readonly EntityRepository $customFieldSetRepository,
        private readonly EntityRepository $customFieldRepository,
        private readonly EntityRepository $customFieldSetRelationRepository,
    ) {}

    public function sync(array $config, Context $context): void
    {
        foreach ($config as $setDef) {
            $setName = (string)($setDef['name'] ?? '');

            if ($setName === '') {
                throw new \InvalidArgumentException('Custom field set "name" is required.');
            }

            $setId = DeterministicId::setId($setName);

            $this->customFieldSetRepository->upsert([[
                'id' => $setId,
                'name' => $setName,
                'config' => $setDef['config'] ?? [],
            ]], $context);

            $expectedEntities = $this->normalizeExpectedEntities($setDef);

            $relUpserts = [];
            foreach ($expectedEntities as $entityName => $_) {
                $relUpserts[] = [
                    'id' => DeterministicId::relationId($setName, $entityName),
                    'customFieldSetId' => $setId,
                    'entityName' => $entityName,
                ];
            }
            if ($relUpserts !== []) {
                $this->customFieldSetRelationRepository->upsert($relUpserts, $context);
            }

            $existingRelations = $this->getExistingRelationsByEntity($setId, $context);
            $relDeletes = [];
            foreach ($existingRelations as $entityName => $relId) {
                if (!isset($expectedEntities[$entityName])) {
                    $relDeletes[] = ['id' => $relId];
                }
            }
            if ($relDeletes !== []) {
                $this->customFieldSetRelationRepository->delete($relDeletes, $context);
            }

            $expectedFields = $this->normalizeExpectedBuiltFieldNames($setDef);

            $fieldUpserts = [];
            foreach ($expectedFields as $builtName => $fieldDef) {
                $type = $fieldDef['type'] ?? null;
                if (!\is_string($type) || $type === '') {
                    throw new \InvalidArgumentException(sprintf(
                        'Custom field "%s" in set "%s" is missing valid "type".',
                        $builtName,
                        $setName
                    ));
                }

                $fieldUpserts[] = [
                    'id' => DeterministicId::fieldId($setName, $builtName),
                    'customFieldSetId' => $setId,
                    'name' => $builtName,
                    'type' => $type,
                    'config' => $fieldDef['config'] ?? [],
                ];
            }
            if ($fieldUpserts !== []) {
                $this->customFieldRepository->upsert($fieldUpserts, $context);
            }

            $existingFields = $this->getExistingFieldsByName($setId, $context);
            $expectedNames = array_fill_keys(array_keys($expectedFields), true);

            $fieldDeletes = [];
            foreach ($existingFields as $existingName => $existingId) {
                if (!isset($expectedNames[$existingName])) {
                    $fieldDeletes[] = ['id' => $existingId];
                }
            }
            if ($fieldDeletes !== []) {
                $this->customFieldRepository->delete($fieldDeletes, $context);
            }
        }
    }

    /**
     * @return array<string,bool> entityName => true
     */
    private function normalizeExpectedEntities(array $setDef): array
    {
        $setName = (string)($setDef['name'] ?? '');
        $out = [];

        foreach (($setDef['relations'] ?? []) as $relDef) {
            $entityName = (string)($relDef['entityName'] ?? '');
            if ($entityName === '') {
                throw new \InvalidArgumentException(sprintf('Relation in set "%s" is missing "entityName".', $setName));
            }
            $out[$entityName] = true;
        }

        return $out;
    }

    /**
     * @return array<string, array<string,mixed>> builtFieldName => fieldDef
     */
    private function normalizeExpectedBuiltFieldNames(array $setDef): array
    {
        $setName = (string)($setDef['name'] ?? '');
        $out = [];

        foreach (($setDef['customFields'] ?? []) as $fieldDef) {
            $shortName = (string)($fieldDef['name'] ?? '');
            if ($shortName === '') {
                throw new \InvalidArgumentException(sprintf('Custom field in set "%s" is missing "name".', $setName));
            }

            $builtName = $setName . '_' . $shortName;
            $out[$builtName] = $fieldDef;
        }

        return $out;
    }

    /**
     * @return array<string,string> builtFieldName => fieldId
     */
    private function getExistingFieldsByName(string $setId, Context $context): array
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('customFieldSetId', $setId));
        $result = $this->customFieldRepository->search($criteria, $context);

        $out = [];
        foreach ($result->getEntities() as $field) {
            /** @var \Shopware\Core\System\CustomField\CustomFieldEntity $field */
            $out[$field->getName()] = $field->getId();
        }
        return $out;
    }

    /**
     * @return array<string,string> entityName => relationId
     */
    private function getExistingRelationsByEntity(string $setId, Context $context): array
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('customFieldSetId', $setId));
        $result = $this->customFieldSetRelationRepository->search($criteria, $context);

        $out = [];
        foreach ($result->getEntities() as $rel) {
            /** @var \Shopware\Core\System\CustomField\Aggregate\CustomFieldSetRelation\CustomFieldSetRelationEntity $rel */
            $out[$rel->getEntityName()] = $rel->getId();
        }
        return $out;
    }

    public function remove(array $config, Context $context): void
    {
        $setDeletes = [];

        foreach ($config as $setDef) {
            $setName = (string)($setDef['name'] ?? '');
            if ($setName === '') {
                continue;
            }

            $criteria = (new Criteria())
                ->addFilter(new EqualsFilter('name', $setName))
                ->setLimit(1);

            $set = $this->customFieldSetRepository->search($criteria, $context)->first();

            if ($set !== null) {
                $setDeletes[] = ['id' => $set->getId()];
            }
        }

        if ($setDeletes !== []) {
            $this->customFieldSetRepository->delete($setDeletes, $context);
        }
    }
}
