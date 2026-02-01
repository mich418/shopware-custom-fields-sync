<?php declare(strict_types=1);

namespace Mich418\ShopwareCustomFieldsSync\Config;

interface CustomFieldsConfigLoaderInterface
{
    /**
     * @return array<int, array<string,mixed>>
     */
    public function load(string $path): array;
}
