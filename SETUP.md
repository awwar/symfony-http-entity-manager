# SymfonyHttpEntityManager

Для синхронизации вашей сущности с внешним Api нужно пройти 3 простых шага:

## Настройка

1) Создать файл `config/packages/symfony_http_entity_manager.yaml`
2) В содержимом указать:

```yaml
symfony_http_entity_manager:
    entity_mapping:
        App\Domain:
            directory: ./src/Domain
```

В entity_mapping перечислить все места, где может быть сущность. Чем точнее вы укажете, тем быстрее пройдёт сборка

## Создание сущности

Допустим у нас есть сущность контакта, которую можно получить по Api в стандарте [JSON API](https://jsonapi.org/)

Это достаточно сложный формат для нашей библиотеки и нам требуется ваша помощь чтоб с ним справится. Сначала посмотрим
на сущность, а потом разберёмся какие аннотации нужно добавить чтоб библиотека смогла синхронизировать её с апи.

```php
class Contact
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

    public function addDeal(SalesapDealEntity $deal): void
    {
        if (in_array($deal, (array) $this->deals)) {
            return;
        }

        $this->deals[] = $deal;
    }
}
```

Достаточно простая сущность, однако имеет в себе массив `deals`  и может хранить в себе `admin`

Чтобы уже начать работать мы можем добавить 4 основные аннотации:
- [HttpEntity](ANNOTATIONS.md#httpentity)
- [FieldMap](ANNOTATIONS.md#fieldmap)
- [RelationMap](ANNOTATIONS.md#relationmap)
- [EntityId](ANNOTATIONS.md#entityid)

По итогу мы уже можем получить сущность и все поля кроме ralations правильно размапятся. 
Однако для полноценной работы нужны другие [аннотации](ANNOTATIONS.md)

```php
#[HttpEntity(name: 'contacts', client: "json_api.client", repository: ContactRepository::class, delete: 'delete-admin/{id}')]
#[UpdateMethod(name: Request::METHOD_PATCH)]
#[GetOneQuery(callback: [IncludesHelper::class, 'calculateIncludes'], args: [self::class])]
#[FilterQuery(['include' => 'admin,deals'])]
#[FilterOneQuery(['include' => 'admin,deals', 'page' => ['size' => 1]])]
class Contact
{
    #[EntityId]
    #[FieldMap(all: 'data.id', preCreate: null)]
    private ?string $id = null;

    #[FieldMap(all: 'data.attributes.first-name')]
    private string $firstName = '';

    #[FieldMap(all: 'data.attributes.sex')]
    #[DefaultValue('u')]
    private string $sex;

    #[FieldMap(all: 'data.attributes.email')]
    private string $email = '';

    #[RelationMap(Deal::class, 'deals', RelationMap::MANY)]
    private Collection $deals;

    #[RelationMap(Admin::class, 'admin', RelationMap::ONE)]
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

    public function addDeal(SalesapDealEntity $deal): void
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
            foreach ($relationData as $name => $rel) {
                $rels = [];
                if (is_iterable($rel)) {
                    foreach ($rel as $value) {
                        $rels [] = [
                            'type' => $value::NAME,
                            'id'   => $value->getId(),
                        ];
                    }
                } else {
                    $rels = ['type' => $rel::NAME, 'id' => $rel->getId()];
                }

                $relationship['relationships'][$name] = ['data' => $rels];
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

## Создание репозитория

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
