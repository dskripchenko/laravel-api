---
title: Prüfung der Auszeichnung
locale: de
status: stable
---

# Das API prüfen

```bash
php artisan api:lint
```

## Wozu das gut ist

Dieses Paket scheitert von Haus aus lautlos — und genau deshalb gibt es diesen
Befehl.

Eine Aktion, deren Controller-Methode umbenannt wurde, antwortet mit **404** —
demselben 404 wie eine falsch getippte URL. In den Logs ist „dieser Endpunkt ist
verschwunden“ damit nicht von „jemand hat Unsinn angefragt“ zu unterscheiden.
Ein unbekannter Typ wird stillschweigend zu `string`. Und ein
`@response 200 {UserResponse}` mit einem nie definierten Template wird zu einem
`$ref` ins Leere — in einer Spezifikation, die trotzdem valide ist.

In all diesen Fällen startet die Anwendung, die Tests sind grün, und der Fehler
erreicht denjenigen, der das API benutzt.

`api:lint` liest die Routen-Tabelle und die Docblock-Auszeichnung mit genau dem
Parser, den auch der OpenAPI-Generator verwendet, und meldet, was sonst
unbemerkt bliebe.

## Optionen

| Option | Wirkung |
|---|---|
| `--api-version=v1` | Nur eine Version prüfen statt aller |
| `--strict` | Auch Warnungen als Fehlschlag werten, nicht nur Fehler |
| `--unrouted` | Zusätzlich öffentliche Controller-Methoden melden, auf die keine Aktion zeigt |
| `--json` | Bericht als JSON ausgeben |

Der Exit-Code ist `1`, sobald es Fehler gibt — mit `--strict` auch bei
Warnungen. Damit taugt der Befehl als CI-Schritt:

```yaml
- run: php artisan api:lint --strict
```

`--unrouted` ist standardmäßig aus: ein Controller darf Hilfsmethoden haben, die
nie Endpunkte werden sollten, und ein Linter, der sich darüber beschwert, ist
einer, den man nicht mehr liest. Der Befehl sagt selbst, dass diese Prüfung
ausgelassen wurde — ein stillschweigend verkleinerter Umfang liest sich wie
„alles in Ordnung“.

## Was geprüft wird

### Die Routen-Tabelle

| Regel | Schwere | Bedeutung |
|---|---|---|
| `action.missing-method` | Fehler | Die Aktion zeigt auf eine Controller-Methode, die es nicht gibt. **Genau der unbemerkte 404.** |
| `action.unreachable-method` | Fehler | Die Methode existiert, ist aber nicht öffentlich oder statisch — sie kann keine Anfrage bedienen |
| `action.unknown-http-method` | Fehler | Ein Verb außerhalb von `laravel-api.available_methods` |
| `controller.missing-class` | Fehler | Der Schlüssel `controller` nennt eine Klasse, die es nicht gibt |
| `controller.missing-key` | Fehler | Dem Controller-Eintrag fehlt der Schlüssel `controller` ganz |
| `middleware.missing-class` | Fehler | Eine Middleware-Klasse der Kaskade existiert nicht |
| `api.missing-class` | Fehler | `getApiVersionList()` verweist auf eine fehlende Klasse |
| `controller.unrouted-method` | Warnung | Eine öffentliche Methode, auf die keine Aktion zeigt (nur mit `--unrouted`) |

Bloße Middleware-Namen ohne Namespace-Trenner bleiben unangetastet: das sind
Router-Gruppen und Aliase, und einen Tippfehler von einer dem Linter unbekannten
Gruppe zu unterscheiden, geht nicht ohne die ganze Anwendung hochzufahren.

### Die Auszeichnung

| Regel | Schwere | Bedeutung |
|---|---|---|
| `tag.malformed` | Fehler | Der Tag-Inhalt lässt sich nicht parsen, und der Generator verwirft ihn kommentarlos |
| `tag.empty` | Warnung | Ein Tag, hinter dem nichts steht |
| `tag.callable-misplaced` | Warnung | Die `[method]`-Form an einem anderen Tag als `@input` |
| `tag.template-misplaced` | Warnung | Die `{Template}`-Form an einem anderen Tag als `@output` |
| `tag.unknown-template` | Fehler | `@input @Model` / `@output @Model[]` nennt ein nicht definiertes Template |
| `tag.callable-missing` | Fehler | `@input [method]` nennt eine Methode, die der Controller nicht hat |
| `tag.unknown-type` | Warnung | Ein Typ außerhalb der bekannten Menge — er wird stillschweigend zu `string` |
| `tag.duplicate-variable` | Warnung | Dieselbe Variable zweimal deklariert; die letzte gewinnt |
| `tag.orphan-nesting` | Warnung | `$address.city` ohne deklariertes `$address` |
| `tag.nesting-type-mismatch` | Warnung | Der Elternteil von `$tags[].id` ist als etwas anderes als `array` deklariert |
| `response.malformed` | Fehler | `@response` lässt sich nicht parsen |
| `response.unknown-template` | Fehler | `@response 200 {Name}` nennt ein nicht definiertes Template |
| `response.impossible-code` | Fehler | Ein Statuscode außerhalb von 100–599 |
| `response.duplicate-code` | Warnung | Zwei Antworten für einen Code; die letzte gewinnt |
| `security.unknown-scheme` | Fehler | `@security Name` oder ein `security`-Schlüssel der Aktion nennt ein nicht definiertes Schema |
| `template.unknown-ref` | Fehler | Ein Template-Feld verweist über `@Other` auf ein Template, das es nicht gibt |
| `default.unknown-variable`, `example.unknown-variable` | Warnung | Vorgabe oder Beispiel für eine Variable ohne `@input` — der Wert wird ignoriert |
| `default.malformed`, `example.malformed` | Fehler | Der Tag-Inhalt lässt sich nicht parsen |

Von Middleware beigesteuerte Eingaben werden berücksichtigt: ein `@default` für
eine dort deklarierte Variable ist legitim und wird nicht gemeldet.

## Den Bericht lesen

```
v1 · user.update
  error    The action points at App\Api\Controllers\UserController::updte(), and there is no such method.  [action.missing-method]
           At runtime this answers 404 — the same 404 as a wrong URL, which is why it goes unnoticed.
  warning  @input $role: the type `enum` is unknown and becomes `string`.  [tag.unknown-type]
           Known types: string, file, number, integer, boolean, array, object.
```

Die Adresse ist der Endpunkt — `Version · Controller.Aktion` — und nicht Datei
und Zeile: die Auszeichnung eines Endpunkts verteilt sich über den Docblock des
Controllers, die Routen-Tabelle der Api-Klasse und die Middleware-Kette. Die
lohnende Frage lautet daher „welcher Endpunkt ist kaputt“.

Jeder Befund trägt einen stabilen Regel-Slug, sodass sich zwei Berichte
zwischen Läufen vergleichen lassen.

## Aus dem Code

Der Linter ist ein Service; der Befehl ist nur eine dünne Hülle darum.

```php
use Dskripchenko\LaravelApi\Services\Linter\OpenApiLinter;

$issues = app(OpenApiLinter::class)->lint();            // alle registrierten Versionen
$issues = app(OpenApiLinter::class)->lint('v1');        // nur eine

// Oder mit einer expliziten Zuordnung, am registrierten Modul vorbei:
$issues = app(OpenApiLinter::class)->lintVersionList(['v1' => MyApi::class]);

foreach ($issues as $issue) {
    $issue->severity;   // 'error' | 'warning'
    $issue->rule;       // 'action.missing-method'
    $issue->where;      // 'v1 · user.update'
    $issue->message;
    $issue->hint;
}
```

## Siehe auch

- [Referenz der Docblock-Tags](docblock-tags.md)
- [Kochbuch](cookbook.md)
