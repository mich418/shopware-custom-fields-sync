<?php declare(strict_types=1);

namespace Mich418\ShopwareCustomFieldsSync\Config;

use Symfony\Component\Yaml\Yaml;

final class CustomFieldsConfigLoader implements CustomFieldsConfigLoaderInterface
{
    public function load(string $path): array
    {
        if (!is_file($path)) {
            throw new \InvalidArgumentException(sprintf('Config file not found: %s', $path));
        }

        $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'php' => $this->loadPhp($path),
            'yaml', 'yml' => $this->loadYaml($path),
            default => throw new \InvalidArgumentException(sprintf('Unsupported config extension "%s" for file: %s', $ext, $path)),
        };
    }

    private function loadPhp(string $path): array
    {
        $data = require $path;

        if (!is_array($data)) {
            throw new \InvalidArgumentException(sprintf('PHP config must return array, got %s (%s)', gettype($data), $path));
        }

        return $data;
    }

    private function loadYaml(string $path): array
    {
        $data = Yaml::parseFile($path);

        if (!is_array($data)) {
            throw new \InvalidArgumentException(sprintf('YAML config must parse to array, got %s (%s)', gettype($data), $path));
        }

        return $data;
    }
}
