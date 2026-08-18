---
title: Проверка разметки
locale: ru
status: stable
---

# Проверка API

```bash
php artisan api:lint
```

## Зачем это нужно

Пакет устроен так, что ошибается молча, — и ровно поэтому нужна команда.

Действие, у которого переименовали метод контроллера, отвечает **404** — тот же
404, что и на опечатку в адресе, так что по логам «эндпоинт пропал» неотличимо
от «спросили ерунду». Неизвестный тип молча превращается в `string`. А
`@response 200 {UserResponse}` со шаблоном, которого нет, даёт `$ref` в
никуда — в спецификации, которая при этом проходит валидацию.

В каждом из этих случаев приложение поднимается, тесты зелёные, а ошибка
доезжает до того, кто пользуется API.

`api:lint` читает карту маршрутов и разметку тем же парсером, которым
пользуется генератор OpenAPI, и докладывает то, что иначе осталось бы
незамеченным.

## Опции

| Опция | Что делает |
|---|---|
| `--api-version=v1` | Проверить одну версию вместо всех |
| `--strict` | Считать провалом и предупреждения, а не только ошибки |
| `--unrouted` | Дополнительно искать публичные методы контроллера, на которые не ведёт ни одно действие |
| `--json` | Отчёт в JSON |

Код возврата `1`, если есть ошибки, — а с `--strict` и если есть
предупреждения. То есть это шаг CI:

```yaml
- run: php artisan api:lint --strict
```

`--unrouted` выключен по умолчанию: в контроллере могут быть вспомогательные
методы, которые эндпоинтами быть и не собирались, а линтер, который ругается на
них, — это линтер, который перестают читать. Команда сама сообщает, что проверка
не выполнялась: молча суженный охват читается как «всё в порядке».

## Что проверяется

### Карта маршрутов

| Правило | Уровень | Смысл |
|---|---|---|
| `action.missing-method` | ошибка | Действие ведёт на метод контроллера, которого нет. **Тот самый незаметный 404.** |
| `action.unreachable-method` | ошибка | Метод есть, но он не публичный или статический — обслужить запрос не сможет |
| `action.unknown-http-method` | ошибка | Глагол вне `laravel-api.available_methods` |
| `controller.missing-class` | ошибка | В ключе `controller` класс, которого нет |
| `controller.missing-key` | ошибка | У записи контроллера вовсе нет ключа `controller` |
| `middleware.missing-class` | ошибка | В каскаде указан несуществующий класс middleware |
| `api.missing-class` | ошибка | `getApiVersionList()` сопоставляет версии отсутствующий класс |
| `controller.unrouted-method` | предупреждение | Публичный метод, на который не ведёт ни одно действие (только с `--unrouted`) |

Короткие имена middleware без разделителя пространств имён не трогаются: это
группы и алиасы роутера, и отличить опечатку от группы, о которой линтер не
знает, нельзя без подъёма всего приложения.

### Разметка

| Правило | Уровень | Смысл |
|---|---|---|
| `tag.malformed` | ошибка | Тело тега не разбирается, и генератор молча его выбрасывает |
| `tag.empty` | предупреждение | Тег, после которого ничего нет |
| `tag.callable-misplaced` | предупреждение | Форма `[method]` не у `@input` |
| `tag.template-misplaced` | предупреждение | Форма `{Template}` не у `@output` |
| `tag.unknown-template` | ошибка | `@input @Model` / `@output @Model[]` ссылается на неопределённый шаблон |
| `tag.callable-missing` | ошибка | `@input [method]` ссылается на метод, которого у контроллера нет |
| `tag.unknown-type` | предупреждение | Тип вне известного набора — молча станет `string` |
| `tag.duplicate-variable` | предупреждение | Переменная объявлена дважды, побеждает последняя |
| `tag.orphan-nesting` | предупреждение | `$address.city` без объявленного `$address` |
| `tag.nesting-type-mismatch` | предупреждение | Родитель `$tags[].id` объявлен не как `array` |
| `response.malformed` | ошибка | `@response` не разбирается |
| `response.unknown-template` | ошибка | `@response 200 {Name}` ссылается на неопределённый шаблон |
| `response.impossible-code` | ошибка | Код вне диапазона 100–599 |
| `response.duplicate-code` | предупреждение | Два ответа на один код, побеждает последний |
| `security.unknown-scheme` | ошибка | `@security Name` или ключ `security` у действия ссылается на неопределённую схему |
| `template.unknown-ref` | ошибка | Поле шаблона через `@Other` ссылается на шаблон, которого нет |
| `default.unknown-variable`, `example.unknown-variable` | предупреждение | Значение по умолчанию или пример для переменной без `@input` — значение игнорируется |
| `default.malformed`, `example.malformed` | ошибка | Тело тега не разбирается |

Поля, которые добавляет middleware, учитываются: `@default` для объявленной там
переменной законен и в отчёт не попадает.

## Как читать отчёт

```
v1 · user.update
  error    The action points at App\Api\Controllers\UserController::updte(), and there is no such method.  [action.missing-method]
           At runtime this answers 404 — the same 404 as a wrong URL, which is why it goes unnoticed.
  warning  @input $role: the type `enum` is unknown and becomes `string`.  [tag.unknown-type]
           Known types: string, file, number, integer, boolean, array, object.
```

Адрес — это эндпоинт (`версия · контроллер.действие`), а не файл и строка:
разметка одного эндпоинта размазана по докблоку контроллера, карте маршрутов
класса Api и цепочке middleware, поэтому отвечать стоит на вопрос «какой
эндпоинт сломан».

У каждой находки есть устойчивый слаг правила — два отчёта можно сравнивать
между прогонами.

## Из кода

Линтер — сервис, команда лишь тонкая обёртка над ним.

```php
use Dskripchenko\LaravelApi\Services\Linter\OpenApiLinter;

$issues = app(OpenApiLinter::class)->lint();            // все зарегистрированные версии
$issues = app(OpenApiLinter::class)->lint('v1');        // только одна

// Либо явной картой, минуя зарегистрированный модуль:
$issues = app(OpenApiLinter::class)->lintVersionList(['v1' => MyApi::class]);

foreach ($issues as $issue) {
    $issue->severity;   // 'error' | 'warning'
    $issue->rule;       // 'action.missing-method'
    $issue->where;      // 'v1 · user.update'
    $issue->message;
    $issue->hint;
}
```

## См. также

- [Справочник по тегам докблока](docblock-tags.md)
- [Рецепты](cookbook.md)
