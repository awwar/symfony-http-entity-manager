<?php

namespace Awwar\SymfonyHttpEntityManager\Service\UOW;

use Awwar\SymfonyHttpEntityManager\Service\Annotation\CreateLayout;
use Awwar\SymfonyHttpEntityManager\Service\Annotation\DefaultValue;
use Awwar\SymfonyHttpEntityManager\Service\Annotation\EmptyValue;
use Awwar\SymfonyHttpEntityManager\Service\Annotation\EntityId;
use Awwar\SymfonyHttpEntityManager\Service\Annotation\FieldMap;
use Awwar\SymfonyHttpEntityManager\Service\Annotation\FilterOneQuery;
use Awwar\SymfonyHttpEntityManager\Service\Annotation\FilterQuery;
use Awwar\SymfonyHttpEntityManager\Service\Annotation\GetOneQuery;
use Awwar\SymfonyHttpEntityManager\Service\Annotation\HttpEntity;
use Awwar\SymfonyHttpEntityManager\Service\Annotation\ListDetermination;
use Awwar\SymfonyHttpEntityManager\Service\Annotation\RelationMap;
use Awwar\SymfonyHttpEntityManager\Service\Annotation\RelationMapper;
use Awwar\SymfonyHttpEntityManager\Service\Annotation\UpdateLayout;
use Awwar\SymfonyHttpEntityManager\Service\Annotation\UpdateMethod;
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

    private array $relationsMap = [];

    private Closure $relationMapper;

    private Closure $listDetermination;

    private bool $useDiffOnUpdate = true;

    private object $emptyInstance;

    private object $proxy;

    private array $filterQuery = [];

    private array|string $getOneQuery = [];

    private array|string $filterOneQuery = [];

    /**
     * @param class-string<object> $className
     * @param array $annotations
     * @throws ReflectionException
     */
    public function __construct(string $className, array $annotations)
    {
        $id = $annotations[EntityId::class][0]['targetName'];

        if ($id === null) {
            throw new Exception("The EntityId annotation must be set on http entity!");
        }

        $this->idProperty = $id;

        $this->useDiffOnUpdate = (bool) $annotations[UpdateMethod::class][0]['data']['use_diff'];

        $this->filterQuery = $annotations[FilterQuery::class][0]['data'];

        $this->getOneQuery = $annotations[GetOneQuery::class][0]['data'];

        $this->filterOneQuery = $annotations[FilterOneQuery::class][0]['data'];

        $proxyClass = Generator::PROXY_NAMESPACE . "{$className}Proxy";

        /** @var class-string $proxyClass */
        $proxyClass = str_replace('/', '\\', $proxyClass);

        $this->proxy = (new ReflectionClass($proxyClass))->newInstanceWithoutConstructor();

        $this->emptyInstance = (new ReflectionClass($className))->newInstanceWithoutConstructor();

        $metadata = $annotations[HttpEntity::class][0]['data'];
        $this->name = $metadata['name'];

        $updateMethod = $annotations[UpdateMethod::class][0]['data']['name'];
        $this->client = new Client($metadata['client'], $updateMethod, $this->name);
        $this->repository = $metadata['repository'];
        $this->getOnePattern = (string) $metadata['one'];
        $this->getListPattern = (string) $metadata['list'];
        $this->createPattern = (string) $metadata['create'];
        $this->updatePattern = (string) $metadata['update'];
        $this->deletePattern = (string) $metadata['delete'];

        $mappers = $annotations[FieldMap::class];

        foreach ($mappers as $map) {
            $filed = $map['targetName'];

            foreach ($map['data'] as $condition => $path) {
                if ($path === null) {
                    continue;
                }
                $this->filedMap[$condition][$filed] = $path;
            }
            $this->properties[] = $filed;
        }

        $defaults = $annotations[DefaultValue::class];

        foreach ($defaults as $default) {
            $filed = $default['targetName'];
            $value = $default['data']['value'];

            $this->defaultMap[$filed] = $value;
        }

        $relations = $annotations[RelationMap::class];

        foreach ($relations as $relation) {
            if (!$filed = $relation['targetName']) {
                continue;
            }
            $this->properties[] = $filed;
            $this->relationsMap[$filed] = $relation['data'];
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

        if ($methodName = $annotations[RelationMapper::class][0]['targetName']) {
            $this->relationMapper = (function (...$payload) use ($methodName) {
                return $this->{$methodName}(...$payload);
            })->bindTo($this->emptyInstance, $this->emptyInstance);
        }

        if ($methodName = $annotations[CreateLayout::class][0]['targetName']) {
            $this->createLayout = (function (...$payload) use ($methodName) {
                return $this->{$methodName}(...$payload);
            })->bindTo($this->emptyInstance, $this->emptyInstance);
        }

        if ($methodName = $annotations[UpdateLayout::class][0]['targetName']) {
            $this->updateLayout = (function (...$payload) use ($methodName) {
                return $this->{$methodName}(...$payload);
            })->bindTo($this->emptyInstance, $this->emptyInstance);
        }

        if ($methodName = $annotations[ListDetermination::class][0]['targetName']) {
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
            return EmptyValue::class;
        }

        return $this->defaultMap[$property];
    }

    public function getProperties(): array
    {
        return $this->properties;
    }

    public function getFilterQuery(): mixed
    {
        return $this->filterQuery;
    }

    public function getGetOneQuery(): mixed
    {
        return $this->getOneQuery;
    }

    public function getFilterOneQuery(): mixed
    {
        return $this->filterOneQuery;
    }

    public function getRelationsMap(): array
    {
        return $this->relationsMap;
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
