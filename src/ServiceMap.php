<?php

declare(strict_types=1);

namespace Proget\PHPStan\Yii2;

use PhpParser\Node;

final class ServiceMap
{
    /**
     * @var string[]
     */
    private $services = [];

    /**
     * @var (string[]|object)[]
     */
    private $components = [];

    public function __construct(string $configPath)
    {
        if (!file_exists($configPath)) {
            throw new \InvalidArgumentException(sprintf('Provided config path %s must exist', $configPath));
        }

        \defined('YII_ENV_DEV') or \define('YII_ENV_DEV', false);
        \defined('YII_ENV_PROD') or \define('YII_ENV_PROD', false);
        \defined('YII_ENV_TEST') or \define('YII_ENV_TEST', true);

        $config = require $configPath;
        foreach ($config['container']['singletons'] ?? [] as $id => $service) {
            $this->addServiceDefinition($id, $service);
        }
        foreach ($config['container']['definitions'] ?? [] as $id => $service) {
            $this->addServiceDefinition($id, $service);
        }

        foreach ($config['components'] ?? [] as $id => $component) {
            if (is_object($component)) {
                $this->components[$id] = $component;
                continue;
            }

            if (!is_array($component)) {
                throw new \RuntimeException(sprintf('Invalid value for component with id %s. Expected object or array.', $id));
            }

            if (null !== $class = $component['class'] ?? null) {
                $this->components[$id]['class'] = $class;
            }
        }
    }

    public function getServiceClassFromNode(Node $node): ?string
    {
        if ($node instanceof Node\Scalar\String_ && isset($this->services[$node->value])) {
            return $this->services[$node->value];
        }

        return null;
    }

    public function getComponentClassById(string $id): ?string
    {
        // Special case in which the component is already initialized
        if (isset($this->components[$id]) && is_object($this->components[$id])) {
            return get_class($this->components[$id]);
        }

        return $this->components[$id]['class'] ?? null;
    }

    /**
     * @param string|\Closure|array<mixed> $service
     *
     * @throws \ReflectionException
     */
    private function addServiceDefinition(string $id, $service): void
    {
        if ($service instanceof \Closure || \is_string($service)) {
            $returnType = (new \ReflectionFunction($service))->getReturnType();
            if (!$returnType instanceof \ReflectionNamedType) {
                throw new \RuntimeException(sprintf('Please provide return type for %s service closure', $id));
            }

            $this->services[$id] = $returnType->getName();
        } else {
            $this->services[$id] = $service['class'] ?? $service[0]['class'];
        }
    }
}
