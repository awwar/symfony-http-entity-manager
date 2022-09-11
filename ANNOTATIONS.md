## HttpEntity

Require: <mark>YES</mark>

Target: `class`

Http entity label

```php
#[HttpEntity(name: 'contacts', client: "json_api.client", repository: ContactRepository::class, delete: 'delete-user/{id}')]
```

`name` is the name of the entity. Participates in the formation of the resource url. Default `GET /{name}/{id}`
or `POST /{name}`

`client` - the http client that will be used for all requests. Required
interface `Symfony\Contracts\HttpClient\HttpClientInterface`. You can use scoped_client

`repository` - default repository, optional

`delete`,`update`,`create`,`list`,`one` - url redefinition for specific actions. For id substitution use `{id}`

## EntityId

Require: <mark>YES</mark>

Target: `property`

Label of the field responsible for id

```php
#[EntityId]
```

## FieldMap

Require: <mark>YES</mark>

Target: `property`

Data mapping label (not for nested entities)

```php
#[FieldMap(all: 'data.id', preCreate: null)]
```

Dot Notation is used to specify the path to the data from the request. That is, if after updating the entity we receive
an answer from the server

```json
{
  "data": {
    "id": 123
  }
}
```

To map it, you need to write `postUpdate: 'data.id'`. Similarly, and vice versa, if we send update to the server, then
you need to write `preUpdate: 'data.id'` and get the above json.

There are 8 mapping settings in total:

`all` - for all actions

`pre` - only in actions starting with pre

`post` - only in actions starting with post

`preCreate` - create a request before creating an entity

`postCreate` - parsing the response after the entity has been created

`preUpdate` - create a query before updating the entity

`postUpdate` - parsing the response after updating the entity

`postRead` - parsing the response after requesting an entity

For complex combinatorics, use the rule:

`preCreate` > `pre` > `all`

## RelationMap

Require: <mark>YES</mark>

Target: `property`

Related Entity Mapping Label

```php
#[RelationMap(Deal::class, 'deals', RelationMap::MANY)]
```

`class` - FQCN of the entity your entity refers to (this should also be `HttpEntity`)

`name` - the alias of the related entity

`expects` - if collection then `RelationMap::MANY`. If one, then `RelationMap::ONE`

## RelationMapper

Require: <mark>Only if there are entities</mark>

Target: `method`

A callback that will help the library parse nested objects correctly.

The mapper takes the data received from the server and the name of the nested entity.

That is, you have a user, he has transactions

In the user, you marked transactions with the attribute

```php
#[RelationMap(Deal::class, 'deals', RelationMap::MANY)]
```

Then the whole response will come to the mapper, and the name that you wrote in the second argument of RelationMap will
come to `$name`

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

`FullData` - передать данные вложенной сущности в аргумент конструктора в том формате, который вы хотели бы получить при
отдельном запросе для этого объекта. Допустим, вы хотите выкинуть данные для `сделок`, а потом вставить `FullData`
данные в формате, который поставляется с `GET /deals/123`

`Reference` - if in a nested entity you get not data, but only their id - put this id in `Reference`. Then lazy loading
will work and the object will be fully loaded when accessing any unloaded property.

## ListDetermination

Require: <mark>YES</mark>

Target: `method`

Callback to identify entities from the list of entities

Suppose when receiving one entity (`GET /user/123`) the response from the server looks like this

```json
{
  "data": {
    "id": 123,
    "name": "ivan"
  }
}
```

And when getting a list of entities (`GET /user/`) like this:

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

The mapper should look like this:

```php
    #[ListDetermination]
    protected function list(array $data): iterable
    {
        foreach ($data['data'] as $element) {
            yield new Data(['data' => $element], $data['pagination']['next']);
        }
    }
```

Throw out a Data object that will contain an array of data in the first argument of the constructor, similar to what you
want to get when querying a single entity. And in the second - a link to the next page (or `null` if it does not exist)

## UpdateMethod

Require: <mark>NO</mark>

Target: `class`

Specifies which HTTP method to update (PATCH or PUT)

```php
#[UpdateMethod(name: Request::METHOD_PATCH)]
```

`name` - HTTP method (default `METHOD_PATCH`)

`useDiff` - when updating, whether to send only changes or all new state (true by default, i.e. "only changes")

## GetOneQuery

## FilterQuery

## FilterOneQuery

Require: <mark>NO</mark>

Target: `class`

An array with the http request that will be merged with the overall http request when "requesting", "filtering", and "
filtering one". request respectively

```php
#[FilterOneQuery(['include' => 'user,deals', 'page' => ['size' => 1]])]
#[GetOneQuery(callback: ['class', 'method'], args: [1, 2, 3])]
```

`query` - array of http query

`callback` - callback array (`[class, method]` or `[method]`) which should return an array of http query

`args` - arguments for callback

`query` > `callback`

## DefaultValue

Require: <mark>NO</mark>

Target: `property`

Default value if key was not found in response

```php
#[DefaultValue(null)]
```

## UpdateLayout

## CreateLayout

Require: <mark>NO</mark>

Target: `method`

Callback function for pre-creating an update request or creating an entity

```php
    #[UpdateLayout]
    #[CreateLayout]
    protected function layout(
        self $entity,
        array $nonRelationChanges = [],
        array $relationChanges = [],
        array $entityData = [],
        array $relationData = [],
    ): array {
        return ['type' => 'my_type', 'data' => ['id' => $entity->id]];
    }
```

`entity` - current entity (try not to use `this` in this method)

`entityChanges` - not relationship changes

`relationChanges` - relationship changes

`entityData` - actual field data

`relationData` - actual relationship data