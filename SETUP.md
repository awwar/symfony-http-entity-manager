# SymfonyHttpEntityManager

To synchronize your entity with an external API, you need to go through 3 simple steps:

## Setting up

1) Create file `config/packages/symfony_http_entity_manager.yaml`
2) Specify in the content:

```yaml
symfony_http_entity_manager:
    entity_mapping:
        App\Domain:
            directory: ./src/Domain
```

In entity_mapping list all places where the entity can be. The more precise you specify, the faster the assembly will
be.

## Create entity

Let's say we have a contact entity that can be obtained via Api in the [JSON API] standard (https://jsonapi.org/)

This is a rather complex format for our library, and we need your help to cope with it. Let's see first on the entity,
and then we will figure out what annotations need to be added so that the library can synchronize it with the api.

```php
class contact
{
    private ?string $id = null;

    private string $firstName = '';

    private string $sex;

    private string $email = '';

    private array $deals;

    private ?Admin $admin = null;

    public function __construct()
    {
        $this->deals = [];
    }
    
    public static function create(string $firstName, string $sex, string $email): self
    {
        $contact = new self();
        $contact->firstName = $firstName;
        $contact->sex = $sex;
        $contact->email = $email;
        
        return $contact;
    }

    public function changeEmail(string $newEmail): void
    {
        $this->email = $newEmail;
    }

    public function addDeal(Deal $deal): void
    {
        if (in_array($deal, (array) $this->deals)) {
            return;
        }

        $this->deals[] = $deal;
    }
}
```

A fairly simple entity, but it has an array of `deals` and can store `admin`

To get started we can add 4 main annotations:

- [HttpEntity](ANNOTATIONS.md#httpentity)
- [DataField](ANNOTATIONS.md#datafield)
- [RelationField](ANNOTATIONS.md#relationfield)
- [EntityId](ANNOTATIONS.md#entityid)

As a result, we can already get the entity. All fields except relations will be mapped correctly. However, for full
performance need other [annotations](ANNOTATIONS.md)

```php
#[HttpEntity(name: 'contacts', client: "json_api.client", repository: ContactRepository::class, delete: 'delete-admin/{id}')]
#[UpdateMethod(name: Request::METHOD_PATCH)]
#[GetOneQuery(callback: [IncludesHelper::class, 'calculateIncludes'], args: [self::class])]
#[FilterQuery(['include' => 'admin,deals'])]
#[FilterOneQuery(['include' => 'admin,deals', 'page' => ['size' => 1]])]
class Contact
{
    #[EntityId]
    #[DataField(all: 'data.id', preCreate: null)]
    private ?string $id = null;

    #[DataField(all: 'data.attributes.first-name')]
    private string $firstName = '';

    #[DataField(all: 'data.attributes.sex')]
    #[DefaultValue('u')]
    private string $sex;

    #[DataField(all: 'data.attributes.email')]
    private string $email = '';

    #[RelationField(Deal::class, 'deals', RelationField::MANY)]
    private Collection $deals;

    #[RelationField(Admin::class, 'admin', RelationField::ONE)]
    private ?Admin $admin = null;

    public function __construct()
    {
        $this->deals = new GeneralCollection();
    }
    
    public static function create(string $firstName, string $sex, string $email): self
    {
        $contact = new self();
        $contact->firstName = $firstName;
        $contact->sex = $sex;
        $contact->email = $email;
        
        return $contact;
    }

    public function changeEmail(string $newEmail): void
    {
        $this->email = $newEmail;
    }

    public function addDeal(Deal $deal): void
    {
        if (in_array($deal, (array) $this->deals)) {
            return;
        }

        $this->deals[] = $deal;
    }
    
    #[ListDetermination]
    protected function list(array $data): iterable
    {
        foreach ($data['data'] as $element) {
            yield new Data(['data' => $element, 'included' => $data['included']], $data['links']['next']);
        }
    }
    
    #[UpdateLayout]
    #[CreateLayout]
    protected function layout(
        self $entity,
        array $entityChanges = [],
        array $relationChanges = [],
        array $entityData = [],
        array $relationData = [],
    ): array {
        $relationship = [];
        $ids = [];

        if (false === empty($relationChanges)) {
            foreach ($relationData as $name => $relation) {
                $jsonApiRelation = [];
                if ($relation instanceof Collection) {
                    foreach ($relation as $value) {
                        $jsonApiRelation [] = [
                            'type' => $value::NAME,
                            'id'   => $value->getId(),
                        ];
                    }
                } else {
                    $jsonApiRelation = ['type' => $rel::NAME, 'id' => $rel->getId()];
                }

                $relationship['relationships'][$name] = ['data' => $jsonApiRelation];
            }
        }

        if ($entity->id !== null) {
            $ids = ['id' => $entity->id];
        }

        return [
            'data' => ['type' => 'contacts'] + $relationship + $ids,
        ];
    }
    
    #[RelationMapper]
    protected function mapper(array &$data, string $name): iterable
    {
        $relationships = $mainData['relationships'][$name]['data'] ?? [];

        foreach ($relationships as $rel) {
            $id = $rel['id'];
            if ($relations = $included[$rel['type']] ?? false) {
                yield new FullData(['data' => $relations[$id], 'included' => $data['included']]);
            } else {
                yield new Reference($id);
            }
        }
    }
}
```

## Create a repository

```php
use Awwar\SymfonyHttpEntityManager\Service\Http\HttpEntityManagerInterface;
use Awwar\SymfonyHttpEntityManager\Service\Http\HttpRepository;

/**
 * @method Contact      find($id, array $criteria = [])
 * @method Contact[]    filter(array $criteria)
 * @method Contact      filterOne(array $criteria)
 */
class ContactRepository extends HttpRepository
{
    public function __construct(HttpEntityManagerInterface $httpEntityManager)
    {
        parent::__construct($httpEntityManager, Contact::class);
    }
}
```
