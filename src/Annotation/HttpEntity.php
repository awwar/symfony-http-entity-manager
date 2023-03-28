<?php

namespace Awwar\SymfonyHttpEntityManager\Annotation;

use Attribute;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Attribute(Attribute::TARGET_CLASS)]
class HttpEntity implements CacheableAnnotation
{
    private string $getOnePattern;
    private string $getListPattern;
    private string $createPattern;
    private string $updatePattern;
    private string $deletePattern;

    public function __construct(
        private string $name,
        private string $client = HttpClientInterface::class,
        private ?string $repository = null,
        ?string $one = null,
        ?string $list = null,
        ?string $create = null,
        ?string $update = null,
        ?string $delete = null,
    ) {
        $this->getOnePattern = $one ?? "$name/{id}";
        $this->getListPattern = $list ?? "$name";
        $this->createPattern = $create ?? "$name";
        $this->updatePattern = $update ?? "$name/{id}";
        $this->deletePattern = $delete ?? "$name/{id}";
    }

    public function toArray(): array
    {
        return [
            'name'       => $this->name,
            'client'     => $this->client,
            'repository' => $this->repository,
            'one'        => $this->getOnePattern,
            'list'       => $this->getListPattern,
            'create'     => $this->createPattern,
            'update'     => $this->updatePattern,
            'delete'     => $this->deletePattern,
        ];
    }

    public static function getDefault(): array
    {
        return [];
    }
}
