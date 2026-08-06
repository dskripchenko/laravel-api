<!--
REDDIT POST DRAFT — for r/laravel (primary) and/or r/PHP
Read before posting:
- This is a self-post (text), NOT a link post. Self-promo reads better as a story.
- Disclose you're the author (done in body). Most subs require it.
- Add flair: "Package" / "Show & Tell" on r/laravel if available.
- Don't paste the same text in both subs the same day — space them out, and
  tweak the opening line for each. Better: post to one, see how it lands.
- Reply to EVERY comment in the first few hours. Reddit rewards author engagement;
  that's where adoption actually comes from, not the post itself.
- Do NOT lead with the repo link. Link goes at the end.

==== TITLE OPTIONS (pick one) ====
A) I got tired of my OpenAPI docs drifting from my code, so my Laravel package generates them from the PHPDoc you already write
B) Versioned Laravel APIs without copy-pasting controllers between v1 and v2 — package I maintain
C) [Show & Tell] OpenAPI 3.0 from plain PHPDoc, API versioning via inheritance — would love you to tell me why this is a bad idea
-->

Two things have annoyed me on every Laravel API I've built:

1. **The OpenAPI spec drifts from the code** the second I merge. Annotation libraries (`#[OA\Get(...)]`, big YAML files) make me describe each endpoint twice — once in the actual code, once in attributes that rot.
2. **Versioning means copy-paste.** `v2` needs to change 3 endpoints but keep the other 20, so I either duplicate a `V2/` folder (now bugfixes live in two places) or sprinkle `if ($version === 2)` into controllers.

So I've been maintaining a package built around a different bet: **the controller already describes itself.** You write a normal method with a normal PHPDoc, and it derives the route, the request/response schema, and the OpenAPI doc from that. Versioning is just PHP inheritance — `ApiV2 extends ApiV1`, override or disable the actions that changed, the rest is inherited.

Concretely: you write this docblock on a controller method…

```php
/**
 * List tasks
 *
 * @input string $status Filter by status [open,done]
 * @output integer $id Task id
 * @output string $title Task title
 */
```

…and you get the route, an `{open,done}` enum in the spec, the response schema, and an interactive docs UI at `/api/doc` — generated, so it can't go out of sync. No annotations to learn, no YAML to maintain.

**Where it won't fit:** it's opinionated. Responses are wrapped in a fixed envelope (`{success, payload}`), and if you genuinely like attribute-driven specs, you'll miss them here — this is the opposite philosophy. It's also a routing/docs layer, not a full framework.

I'm the author; it's MIT, works on Laravel 11–13, and has a CI matrix + decent test coverage. I'm not trying to sell anything — I'd honestly like the criticism. **What's the strongest argument *against* deriving docs from docblocks instead of explicit annotations?** That's the design decision I'm least sure about, and I'd rather hear it from people who've maintained big APIs than find out in three years.

- Repo (MIT, Laravel 11–13): https://github.com/dskripchenko/laravel-api
- Full step-by-step walkthrough: https://dev.to/dskripchenko/build-a-versioned-laravel-api-with-auto-generated-openapi-docs-in-10-minutes-2d19

Happy to answer anything in the comments.
