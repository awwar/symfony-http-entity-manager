# Get resource

```php
$users = $this->httpEntityManager->getRepository(User::class);

$user = $users->find(123);
```

```bash
curl --request GET \
  --url 'https://api.mysite.com/users/123'
```

```php
$user = $users->find(123, ['my_http_query' => ['sub' => 'value']]);
```

```bash
curl --request GET \
  --url 'https://api.mysite.com/users/123?my_http_query[sub]=value'
```

```php
try {
    $user = $users->find(1234);
} catch (NotFoundException $exception) {
   # not found
}
```

# Resource filtering

```php
$users = $this->httpEntityManager->getRepository(User::class);

try {
    $user = $users->filterOne(['email' => 'user@mail.com']);
} catch (NotFoundException $exception) {
   # not found
}
```

```bash
curl --request GET \
  --url 'https://api.mysite.com/users?email=user@mail.com'
```

```php
$adultUsers = $users->filter(['age:grt' => 18, 'limit' => 1000]);
```

```bash
curl --request GET \
  --url 'https://api.mysite.com/users?age:grt=18&limit=1000'
```

```php
foreach ($adultUsers as $user) {
    dump($user->firstName());
}
```

# Add resource

```php
$users = $this->httpEntityManager->getRepository(User::class);
$curators = $this->httpEntityManager->getRepository(Curator::class);

$anna = $curators->find(123);

$ivan = User::create('Ivan', 18);
$ivan->setCurator($anna);

$this->httpEntityManager->persist($ivan);
$this->httpEntityManager->flush();

//or

$users->add($ivan, flush: true);
```

```bash
curl --request GET \
  --url 'https://api.mysite.com/curators/123'

curl --request POST \
  --url https://api.mysite.com/users \
  --data '{
    "first_name": "Ivan",
    "age": "18",
    "relations": [
       {"name": "curator", "id": 123}
    ]
'
```

# Resource update

```php
$users = $this->httpEntityManager->getRepository(User::class);

$sasha = $users->find(124);

# response: 
# {
#   "first_name": "Sasha",
#   "sex": "male",
#   "age": 25
# }

$sasha->setAge(25);
$sasha->setSex("female");

$this->httpEntityManager->flush();

# only gender needs to be updated, age stays the same
```

```php
$users = $this->httpEntityManager->getRepository(User::class);
$deals = $this->httpEntityManager->getRepository(Deal::class);

$sasha = $users->find(124);

$sashaDealsCollection = $sasha->getDeals();

$deal = $deals->find(322);

$sashaDealsCollection->add($deal);

$this->httpEntityManager->flush();
```

```bash
curl --request GET \
  --url 'https://api.mysite.com/users/124'
  
curl --request GET \
  --url 'https://api.mysite.com/deals/322'
  
curl --request PATCH \
  --url https://api.mysite.com/users/124 \
  --data '{
    "relations": [
       {"name": "deal", "id": 322}
    ]
'
```

# Deleting a resource

```php
$users = $this->httpEntityManager->getRepository(User::class);

$sasha = $users->find(124);

$this->httpEntityManager->remove($sasha);
$this->httpEntityManager->flush();

// or

$users->remove($sasha, flush: true);
```

```bash
curl --request DELETE \
  --url 'https://api.mysite.com/users/124'
```