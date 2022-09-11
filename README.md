# Description

A library that allows you to work with external APIs in the same way that a Symfony Doctrine ORM works with databases.

for example:

```php
$users = $this->httpEntityManager->getRepository(User::class);
$admins = $this->httpEntityManager->getRepository(Admin::class);

$sasha = $users->find(124);
$alex = $admins->filterOne(['filter' => ['specialization' => 'cinema']]);

$deal = Deal::create($sasha, $alex, "buying a movie ticket", 30);

$sasha->getDeals()->add($deal);

$alex->setSpecialization('cinema');

$this->httpEntityManager->persist($deal);
$this->httpEntityManager->flush();
```

real requests:

```bash
# get customer
curl --request GET \
  --url 'https://api.mysite.com/users/124'
  
# get responsible
curl --request GET \
  --url 'https://api.mysite.com/admins?filter[specialization]=cinema'
  
# create deal
curl --request POST \
  --url https://api.mysite.com/deals/ \
  --data '{
    "amount": 30,
    "title": "buying a movie ticket",
    "relations": [
       {"name": "customer", "id": 124}
       {"name": "responsible", "id": 555}
    ]
'

# add created deal to customer
curl --request PATCH \
  --url https://api.mysite.com/users/124 \
  --data '{
    "relations": [
       {"name": "deal", "id": 322}
    ]
'
```

**As we can see, all the changes were applied in the right order, and the changes by Alex did not apply at all, because
we didn't change the value of his specialization. All thanks to UnitOfWork, which tracks changes and executes them in
the right sequence and does not allow unnecessary changes that could affect system performance.**

# Looks simple? See more!

- [Setup](SETUP.md)
- [Annotations](ANNOTATIONS.md)
- [Examples of use](EXAMPLES.md)

# ToDo:

1) Implement work with formats other than "json"
2) When deleting a connection with an entity, a request to delete this relation is not sent
3) Late proxying. If there is a custom entity, and it has a collection of deals, to get a collection of proxy deals, you
   need to pass at least an array of their IDs. However, it is known about such api where the user can be obtained
   via `GET /users/123` and his deals via `GET /users/123/deals`. To do this, you need to implement late proxying.
4) Implement the `em->merge()` method