# Shopware Custom Fields Sync Bundle

A Symfony bundle for **synchronizing Shopware custom field sets and fields from configuration files**.

The bundle treats configuration as the **single source of truth**:
- custom field sets, fields and relations defined in config are **created or updated**
- anything that exists in the database but **is not present in config is removed**
- changes in field configuration (labels, components, options, etc.) are **always applied**
- deterministic UUIDs are generated from names

The bundle is designed to be used **inside Shopware plugins** and triggered from plugin lifecycle hooks (`install`, `update`).

---

## Features

- Supports **Shopware 6.6+**
- Synchronizes:
  - custom field sets
  - custom fields
  - entity relations
- Supports config written in:
  - **PHP**
  - **YAML**
- Deterministic IDs based on names (stable across environments)
- No runtime options – simple and predictable behavior

> ⚠️ Renaming a custom field changes its technical name.
> Existing values stored in entity `custom_fields` JSON will **not** be migrated automatically.
> If you need data migration, handle it separately in your plugin.

---

## Installation

Install via Composer:

```bash
composer require mich418/shopware-custom-fields-sync
```

## Configuration format

Config file can define one or more custom field sets. Shopware Custom Fields Sync provides support for PHP and Yaml config format

### PHP config example

```php
<?php

use Shopware\Core\System\CustomField\CustomFieldTypes;

return [
    [
        'name' => 'product_extra',
        'config' => [
            'label' => [
                'en-GB' => 'Product Extra',
            ],
        ],
        'relations' => [
            ['entityName' => 'product'],
        ],
        'customFields' => [
            [
                'name' => 'subtitle',
                'type' => CustomFieldTypes::TEXT,
                'config' => [
                    'label' => [
                        'en-GB' => 'Subtitle',
                    ],
                    'componentName' => 'sw-text-field',
                    'customFieldPosition' => 1,
                ],
            ],
        ],
    ],
];
```

### Yaml config example

```yaml
- name: product_extra
  config:
    label:
      en-GB: Product Extra
  relations:
    - entityName: product
  customFields:
    - name: subtitle
      type: text
      config:
        label:
          en-GB: Subtitle
        componentName: sw-text-field
        customFieldPosition: 1
```

### Notes

Field technical names are generated as: `{setName}_{fieldName}`

In YAML, type must be a valid Shopware custom field type string (text, int, bool, etc.).

## Using the bundle in a Shopware plugin

### 1. Place the config file in your plugin

A example, you can put the file with a config in following path:

```bash
custom/plugins/MyPlugin/src/Resources/custom-fields.php
```

### 2. Trigger synchronization in plugin lifecycle

```php
<?php declare(strict_types=1);

namespace MyPlugin;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;

use Mich418\ShopwareCustomFieldsSync\Sync\CustomFieldSetSynchronizerInterface;
use Mich418\ShopwareCustomFieldsSync\Config\CustomFieldsConfigLoaderInterface;

final class MyPlugin extends Plugin
{
    public function install(InstallContext $installContext): void
    {
        parent::install($installContext);
        $this->syncCustomFields($installContext->getContext());
    }

    public function update(UpdateContext $updateContext): void
    {
        parent::update($updateContext);
        $this->syncCustomFields($updateContext->getContext());
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);
        $this->removeCustomFields($uninstallContext);
    }

    private function syncCustomFields(\Shopware\Core\Framework\Context $context): void
    {
        $customFieldsConfig =  $this->container
            ->get(CustomFieldsConfigLoaderInterface::class)
            ->load(__DIR__ . '/Resources/custom-fields.php');

        $this->container
            ->get(CustomFieldSetSynchronizerInterface::class)
            ->sync($customFieldsConfig, $context);
    }

    private function removeCustomFields(\Shopware\Core\Framework\Context $context): void
    {
      $customFieldsConfig =  $this->container
            ->get(CustomFieldsConfigLoaderInterface::class)
            ->load(__DIR__ . '/Resources/custom-fields.php');

        $this->container
            ->get(CustomFieldSetSynchronizerInterface::class)
            ->remove($customFieldsConfig, $context);
    }
}
```

## Synchronization rules (important)

During install or update:

- Sets, fields and relations defined in config are upserted
- Any existing field or relation not present in config is deleted
- Field configuration changes are always applied
- Stored values in entity custom_fields JSON are never deleted automatically
