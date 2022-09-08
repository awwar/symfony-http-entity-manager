# Разделы

1) [Настройка](SETUP.md)
2) [Аннотации](ANNOTATIONS.md)
3) [Примеры использования](EXAMPLES.md)

# ToDo:

1) Реализовать работу с другими форматами отличных от "json"
2) При удалении связи из сущности, не отправляется запрос на удаление этой связи
3) Позднее проксирование. Если есть сущность user и у неё есть коллекция deals, чтобы получить прокси-коллекцию deals,
   нужно передать хотя бы массив их id. Однако известно о таких апи, где пользователя можно получить
   через `GET /users/123`, а его сделки через `GET /users/123/deals`. Для этого нужно реализовать позднее проксирование
4) Реализовать `em->merge()` метод