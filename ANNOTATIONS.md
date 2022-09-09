## HttpEntity

Require: <mark>YES</mark>

Target: `class`

Метка http сущности

```php
#[HttpEntity(name: 'contacts', client: "json_api.client", repository: ContactRepository::class, delete: 'delete-user/{id}')]
```

`name` - имя сущности. Участвует в формировании url ресурса. По умолчанию `GET /{name}/{id}` или `POST /{name}`

`client` - http клиент, который будет использоваться для всех запросов. Требуется
интерфейс `Symfony\Contracts\HttpClient\HttpClientInterface`. Можно использовать scoped_client

`repository` - репозиторий по умолчанию, необязательный параметр

`delete`,`update`,`create`,`list`,`one` - переопределение url для конкретных действий. Для подстановки id
использовать `{id}`

## EntityId

Require: <mark>YES</mark>

Target: `property`

Метка поля отвечающего за id

```php
#[EntityId]
```

## FieldMap

Require: <mark>YES</mark>

Target: `property`

Метка маппинга данных (не сущности)

```php
#[FieldMap(all: 'data.id', preCreate: null)]
```

Для указания пути к данным из запроса используется Dot Notation. То есть если после обновления сущности нам придёт ответ
от сервера

```json
{
  "data": {
    "id": 123
  }
}
```

То чтоб его замапить нужно прописать `postUpdate: 'data.id'`. Так же и наоборот если мы отправляем update на сервер то
нужно прописать `preUpdate: 'data.id'` и получить вышеупомянутый json.

Всего существует 8 настроек маппинга:

`all` - при всех действиях

`pre` - только в действиях начинающихся на pre

`post` - только в действиях начинающихся на post

`preCreate` - создание запроса перед созданием сущности

`postCreate` - парсинг ответа после создания сущности

`preUpdate` - создание запроса перед обновлением сущности

`postUpdate` - парсинг ответа после обновления сущности

`postRead` - парсинг ответа после запроса сущности

При сложной комбинаторики пользуйтесь правилом:

`preCreate` > `pre` > `all`

## RelationMap

Require: <mark>YES</mark>

Target: `property`

Метка маппинга связанных сущностей

```php
#[RelationMap(Deal::class, 'deals', RelationMap::MANY)]
```

`class` - FQCN сущности на которую ссылается ваша сущность (это тоже должна быть `HttpEntity`)

`name` - псевдоним связанной сущности

`expects` - если коллекция то `RelationMap::MANY`. Если одна, то `RelationMap::ONE`

## RelationMapper

Require: <mark>Только если есть сущности</mark>

Target: `method`

Callback который поможет библиотеке правильно распарсить вложенные сущности.

Маппер принимает в себя полученные от сервера данные и имя вложенной сущности.

То есть у вас есть user, у него есть deals

В юзере вы пометили deals аттрибутом

```php
#[RelationMap(Deal::class, 'deals', RelationMap::MANY)]
```

То в маппер придёт весь ответ, а в `$name` придёт то имя, которое вы написали во втором аргументе RelationMap

```php
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
```

`FullData` - в аргумент конструктора передайте те данные вложенной сущности, в том формате, которые бы вы хотели
получить при отдельном запросе данной сущности. Допустим вы хотите выкинуть данные для `deals`, то положите в `FullData`
данные в том формате которые приходят с `GET /deals/123`

`Reference` - если во вложенной сущности вам приходят не данные, а только их id - положите этот id в `Reference`. Тогда
сработает ленивая загрузка и сущность подгрузится тогда, когда вы обратитесь к её свойствам

## ListDetermination

Require: <mark>YES</mark>

Target: `method`

Callback для выявления сущностей из списка сущностей

Допустим при получении одной сущности (`GET /user/123`) ответ от сервера выглядит так

```json
{
  "data": {
    "id": 123,
    "name": "ivan"
  }
}
```

А при получении списка сущностей (`GET /user/`) так:

```json
{
  "data": [
    {
      "id": 123,
      "name": "ivan"
    },
    {
      "id": 124,
      "name": "olga"
    }
  ],
  "pagination": {
    "next": "/user?page=2"
  }
}
```

Маппер должен выглядеть так:

```php
    #[ListDetermination]
    protected function list(array $data): iterable
    {
        foreach ($data['data'] as $element) {
            yield new Data(['data' => $element], $data['pagination']['next']);
        }
    }
```

Вы должны выкинуть объект Data в первом аргументе конструктора которого должен лежать массив, похожий на тот, который бы
пришёл при запросе одной сущности. А во втором аргументе ссылка на следующую страницу (или null если её нет)

## UpdateMethod

Require: <mark>NO</mark>

Target: `class`

Указывает каким HTTP методом нужно проводить обновление (PATCH или PUT)

```php
#[UpdateMethod(name: Request::METHOD_PATCH)]
```

`name` - HTTP метод (по умолчанию `METHOD_PATCH`)

`useDiff` - при обновлении отсылать ли только изменения или новое состояние целиком (по умолчанию true, то есть "только
изменения")

## GetOneQuery

## FilterQuery

## FilterOneQuery

Require: <mark>NO</mark>

Target: `class`

Массив с query который будет подмешиваться к query в http при запросе одной, фильтрации и фильтрации одной сущности
соответственно

```php
#[FilterOneQuery(['include' => 'user,deals', 'page' => ['size' => 1]])]
#[GetOneQuery(callback: ['class', 'method'], args: [1, 2, 3])]
```

`query` - массив с http query

`callback` - callback массив (`[class, method]` или `[method]`)  которые должны вернуть массив с http query

`args` - аргументы для callback

`query` > `callback`

## DefaultValue

Require: <mark>NO</mark>

Target: `property`

Значение по умолчанию если при маппинге данных из запроса на сущность, в запросе не было найдено данных

```php
#[DefaultValue(null)]
```

## UpdateLayout

## CreateLayout

Require: <mark>NO</mark>

Target: `method`

Callback для предварительного создания запроса на создание или обновление сущности

```php
    #[UpdateLayout]
    #[CreateLayout]
    protected function layout(
        self $entity,
        array $entityChanges = [],
        array $relationChanges = [],
        array $entityData = [],
        array $relationData = [],
    ): array {
        return ['type' => 'my_type', 'data' => ['id' => $entity->id]];
    }
```

`entity` - текущая сущность (старайтесь в этом методе не использовать `this`)

`entityChanges` - изменение по полям

`relationChanges` - изменения по связям

`entityData` - актуальные данные полей

`relationData` - актуальные данные связей
