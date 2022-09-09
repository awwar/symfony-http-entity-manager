<?php

namespace Awwar\SymfonyHttpEntityManager\Service\UOW;

use Awwar\SymfonyHttpEntityManager\Service\Annotation;
use Awwar\SymfonyHttpEntityManager\Service\Http\HttpRepositoryInterface;
use Awwar\SymfonyHttpEntityManager\Service\ProxyGenerator\Generator;
use Closure;
use Exception;
use ReflectionClass;
use ReflectionException;

class EntityMetadata
{
    private string $name = '';
    private string $idProperty = 'id';
    private ClientInterface $client;
    private ?HttpRepositoryInterface $repository;
    private string $getOnePattern;
    private string $getListPattern;
    private string $createPattern;
    private string $updatePattern;
    private string $deletePattern;

    private Closure $createLayout;
    private Closure $updateLayout;

    private array $properties = [];

    private array $defaultMap = [];

    private array $filedMap = [];

    private array $relationsMapping = [];

    private array $scalarProperties = [];

    private Closure $relationMapper;

    private Closure $listDetermination;

    private bool $useDiffOnUpdate = true;

    private object $emptyInstance;

    private object $proxy;

    private array $filterQuery = [];

    private array $getOneQuery = [];

    private array $filterOneQuery = [];

    /**
     * @param class-string<object> $className
     * @param array $annotations
     * @throws ReflectionException
     */
    public function __construct(string $className, array $annotations)
    {
        $id = $annotations[Annotation\EntityId::class][0]['targetName'];

        if ($id === null) {
            throw new Exception("The EntityId annotation must be set on http entity!");
        }

        $this->idProperty = $id;

        $this->useDiffOnUpdate = (bool) $annotations[Annotation\UpdateMethod::class][0]['data']['use_diff'];

        $this->filterQuery = (array) $annotations[Annotation\FilterQuery::class][0]['data'];

        $this->getOneQuery = (array) $annotations[Annotation\GetOneQuery::class][0]['data'];

        $this->filterOneQuery = (array) $annotations[Annotation\FilterOneQuery::class][0]['data'];

        $proxyClass = Generator::PROXY_NAMESPACE . "{$className}Proxy";

        /** @var class-string $proxyClass */
        $proxyClass = str_replace('/', '\\', $proxyClass);

        $this->proxy = (new ReflectionClass($proxyClass))->newInstanceWithoutConstructor();

        $this->emptyInstance = (new ReflectionClass($className))->newInstanceWithoutConstructor();

        $metadata = $annotations[Annotation\HttpEntity::class][0]['data'];
        $this->name = $metadata['name'];

        $updateMethod = $annotations[Annotation\UpdateMethod::class][0]['data']['name'];
        $this->client = new Client($metadata['client'], $updateMethod, $this->name);
        $this->repository = $metadata['repository'];
        $this->getOnePattern = (string) $metadata['one'];
        $this->getListPattern = (string) $metadata['list'];
        $this->createPattern = (string) $metadata['create'];
        $this->updatePattern = (string) $metadata['update'];
        $this->deletePattern = (string) $metadata['delete'];

        $mappers = $annotations[Annotation\FieldMap::class];

        foreach ($mappers as $map) {
            $filed = $map['targetName'];

            foreach ($map['data'] as $condition => $path) {
                if ($path === null) {
                    continue;
                }
                $this->filedMap[$condition][$filed] = $path;
            }
            $this->properties[] = $filed;
            $this->scalarProperties[] = $filed;
        }

        $defaults = $annotations[Annotation\DefaultValue::class];

        foreach ($defaults as $default) {
            $filed = $default['targetName'];
            $value = $default['data']['value'];

            $this->defaultMap[$filed] = $value;
        }

        $relations = $annotations[Annotation\RelationMap::class];

        foreach ($relations as $relation) {
            if (!$filed = $relation['targetName']) {
                continue;
            }
            $this->properties[] = $filed;
            $this->relationsMapping[$filed] = $relation['data'];
        }

        $this->relationMapper = function (...$payload) {
            return [];
        };

        $this->createLayout = function (...$payload) {
            return [];
        };

        $this->updateLayout = function (...$payload) {
            return [];
        };

        $this->listDetermination = function (array $payload) {
            foreach ($payload as $elem) {
                yield $elem;
            }
        };

        if ($methodName = $annotations[Annotation\RelationMapper::class][0]['targetName']) {
            $this->relationMapper = (function (...$payload) use ($methodName) {
                return $this->{$methodName}(...$payload);
            })->bindTo($this->emptyInstance, $this->emptyInstance);
        }

        if ($methodName = $annotations[Annotation\CreateLayout::class][0]['targetName']) {
            $this->createLayout = (function (...$payload) use ($methodName) {
                return $this->{$methodName}(...$payload);
            })->bindTo($this->emptyInstance, $this->emptyInstance);
        }

        if ($methodName = $annotations[Annotation\UpdateLayout::class][0]['targetName']) {
            $this->updateLayout = (function (...$payload) use ($methodName) {
                return $this->{$methodName}(...$payload);
            })->bindTo($this->emptyInstance, $this->emptyInstance);
        }

        if ($methodName = $annotations[Annotation\ListDetermination::class][0]['targetName']) {
            $this->listDetermination = (function (...$payload) use ($methodName) {
                return $this->{$methodName}(...$payload);
            })->bindTo($this->emptyInstance, $this->emptyInstance);
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isUseDiffOnUpdate(): bool
    {
        return $this->useDiffOnUpdate;
    }

    public function getRepository(): ?HttpRepositoryInterface
    {
        return $this->repository;
    }

    public function getCreateLayout(): callable
    {
        return $this->createLayout;
    }

    public function getUpdateLayout(): callable
    {
        return $this->updateLayout;
    }

    public function getClient(): ClientInterface
    {
        return $this->client;
    }

    public function getIdProperty(): string
    {
        return $this->idProperty;
    }

    public function getUrlForCreate(): string
    {
        return $this->createPattern;
    }

    public function getUrlForList(): string
    {
        return $this->getListPattern;
    }

    public function getUrlForOne(mixed $id = null): string
    {
        return str_replace('{id}', (string) $id, $this->getOnePattern);
    }

    public function getUrlForUpdate(mixed $id = null): string
    {
        return str_replace('{id}', (string) $id, $this->updatePattern);
    }

    public function getUrlForDelete(mixed $id = null): string
    {
        return str_replace('{id}', (string) $id, $this->deletePattern);
    }

    public function getFieldMap(string $name): array
    {
        return $this->filedMap[$name] ?? [];
    }

    public function getDefaultValue(string $property): mixed
    {
        if (array_key_exists($property, $this->defaultMap) === false) {
            return Annotation\EmptyValue::class;
        }

        return $this->defaultMap[$property];
    }

    public function getProperties(): array
    {
        return $this->properties;
    }

    public function getScalarProperties(): array
    {
        return $this->scalarProperties;
    }

    public function getRelationsMapping(): array
    {
        return $this->relationsMapping;
    }

    public function getFilterQuery(): array
    {
        return $this->filterQuery;
    }

    public function getGetOneQuery(): array
    {
        return $this->getOneQuery;
    }

    public function getFilterOneQuery(): array
    {
        return $this->filterOneQuery;
    }

    public function getRelationsMapper(): callable
    {
        return $this->relationMapper->bindTo($this->emptyInstance, $this->emptyInstance)
            ?? throw new \RuntimeException("Unable to bind relationMapper");
    }

    public function getListDetermination(): callable
    {
        return $this->listDetermination->bindTo($this->emptyInstance, $this->emptyInstance)
            ?? throw new \RuntimeException("Unable to bind listDetermination");
    }

    public function getEmptyInstance(): object
    {
        return clone $this->emptyInstance;
    }

    public function getProxy(): object
    {
        return clone $this->proxy;
    }

    public function getProxyClass(): string
    {
        return get_class($this->proxy);
    }

    public function getClassName(): string
    {
        return get_class($this->emptyInstance);
    }
}
