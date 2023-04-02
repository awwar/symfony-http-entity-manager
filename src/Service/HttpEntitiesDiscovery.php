<?php

namespace Awwar\SymfonyHttpEntityManager\Service;

use Awwar\SymfonyHttpEntityManager\Annotation\HttpEntity;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class HttpEntitiesDiscovery
{
    public function __construct(private array $mapping)
    {
    }

    /**
     * @return ReflectionClass[]
     * @throws ReflectionException
     */
    public function searchEntries(): array
    {
        $entries = [];

        foreach ($this->mapping as $namespace => $path) {
            array_push($entries, ...$this->discoverHttpEntities($namespace, $path));
        }

        return $entries;
    }

    /**
     * @return ReflectionClass[]
     * @throws ReflectionException
     */
    private function discoverHttpEntities(string $namespace, string $path): array
    {
        $finder = new Finder();
        $finder->files()->name('/\.php$/')->in($path);

        $entries = [];

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

            $entries[] = $reflection;
        }

        return $entries;
    }
}
