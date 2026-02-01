<?php declare(strict_types=1);

namespace Mich418\ShopwareCustomFieldsSync\Sync;

use Shopware\Core\Framework\Context;

interface CustomFieldSetSynchronizerInterface
{
    /**
     * @param array<int, array<string,mixed>> $config
     */
    public function sync(array $config, Context $context): void;

    /**
     * @param array<int, array<string,mixed>> $config
     */
    public function remove(array $config, Context $context): void;
}
