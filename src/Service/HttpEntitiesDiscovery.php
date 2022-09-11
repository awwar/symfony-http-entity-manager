<?php

namespace Awwar\SymfonyHttpEntityManager\Service;

use Awwar\SymfonyHttpEntityManager\Service\Annotation\HttpEntity;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class HttpEntitiesDiscovery
{
    /**
     * @var ReflectionClass[]
     */
    private array $entitiesReflections = [];

    public function __construct(private array $mapping)
    {
    }

    /**
     * @return ReflectionClass[]
     * @throws ReflectionException
     */
    public function getData(): array
    {
        if (!$this->entitiesReflections) {
            foreach ($this->mapping as $namespace => $path) {
                $this->discoverHttpEntities($namespace, $path);
            }
        }

        return $this->entitiesReflections;
    }

    /**
     * @throws ReflectionException
     */
    private function discoverHttpEntities(string $namespace, string $path): void
    {
        $finder = new Finder();
        $finder->files()->name('/\.php$/')->in($path);

        /** @var SplFileInfo $file */
        foreach ($finder as $file) {
            $filePath = str_replace($path, "", $file->getPath());
            $filePath = str_replace("/", "\\", $filePath);
            /** @var class-string<object> $class */
            $class = $namespace . $filePath . "\\" . $file->getBasename('.php');

            $reflection = new ReflectionClass($class);

            $attributes = $reflection->getAttributes(HttpEntity::class);

            if (count($attributes) !== 1) {
                continue;
            }

            $this->entitiesReflections[] = $reflection;
        }
    }
}
