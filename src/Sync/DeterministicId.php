<?php declare(strict_types=1);

namespace Mich418\ShopwareCustomFieldsSync\Sync;

use Shopware\Core\Framework\Uuid\Uuid;

final class DeterministicId
{
    public static function setId(string $setName): string
    {
        return Uuid::fromStringToHex('customField:set:' . $setName);
    }

    public static function fieldId(string $setName, string $fieldName): string
    {
        return Uuid::fromStringToHex('customField:set:' . $setName . ':field:' . $fieldName);
    }

    public static function relationId(string $setName, string $entityName): string
    {
        return Uuid::fromStringToHex('customField:set:' . $setName . ':relation:' . $entityName);
    }
}
