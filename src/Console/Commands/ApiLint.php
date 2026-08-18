<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelApi\Console\Commands;

use Dskripchenko\LaravelApi\Services\Linter\LintIssue;
use Dskripchenko\LaravelApi\Services\Linter\OpenApiLinter;
use Illuminate\Console\Command;

/**
 * `api:lint` — reads the route map and the docblock markup and reports what
 * would otherwise fail in silence.
 *
 * Built for CI first. Every mistake this catches has the same shape: the
 * application starts, the tests pass, and the damage shows up as a 404 for a
 * caller or a `$ref` into nothing in a published spec.
 */
class ApiLint extends Command
{
    protected $signature = 'api:lint
        {--api-version= : Lint only this API version}
        {--strict : Fail on warnings too, not only on errors}
        {--unrouted : Also report public controller methods no action points at}
        {--json : Emit the report as JSON}';

    protected $description = 'Check the API route map and docblock markup for mistakes that fail silently';

    public function handle(OpenApiLinter $linter): int
    {
        $issues = $linter
            ->withUnroutedMethods((bool) $this->option('unrouted'))
            ->lint($this->option('api-version'));

        $errors = array_values(array_filter($issues, static fn (LintIssue $i): bool => $i->isError()));
        $warnings = array_values(array_filter($issues, static fn (LintIssue $i): bool => ! $i->isError()));

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'errors' => count($errors),
                'warnings' => count($warnings),
                'issues' => array_map(static fn (LintIssue $i): array => $i->toArray(), $issues),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return $this->exitCode($errors, $warnings);
        }

        $this->render($issues);
        $this->summarize($errors, $warnings);

        return $this->exitCode($errors, $warnings);
    }

    /**
     * @param  LintIssue[]  $issues
     */
    private function render(array $issues): void
    {
        if ($issues === []) {
            $this->info('No issues found.');

            return;
        }

        $grouped = [];
        foreach ($issues as $issue) {
            $grouped[$issue->where][] = $issue;
        }

        foreach ($grouped as $where => $group) {
            $this->newLine();
            $this->line("<options=bold>{$where}</>");

            foreach ($group as $issue) {
                $marker = $issue->isError()
                    ? '<fg=red>error</>'
                    : '<fg=yellow>warning</>';

                $this->line("  {$marker}  {$issue->message}  <fg=gray>[{$issue->rule}]</>");

                if ($issue->hint !== null) {
                    $this->line("          <fg=gray>{$issue->hint}</>");
                }
            }
        }

        $this->newLine();
    }

    /**
     * @param  LintIssue[]  $errors
     * @param  LintIssue[]  $warnings
     */
    private function summarize(array $errors, array $warnings): void
    {
        $summary = sprintf('%d error(s), %d warning(s).', count($errors), count($warnings));

        if ($errors !== []) {
            $this->error($summary);
        } elseif ($warnings !== []) {
            $this->warn($summary);
        } else {
            $this->info($summary);
        }

        // The unrouted-method check is off unless asked for. Saying so beats a
        // clean report that quietly covered less than the reader assumes.
        if (! $this->option('unrouted')) {
            $this->line('<fg=gray>Public methods with no action pointing at them were not checked — pass --unrouted for that.</>');
        }
    }

    /**
     * @param  LintIssue[]  $errors
     * @param  LintIssue[]  $warnings
     */
    private function exitCode(array $errors, array $warnings): int
    {
        if ($errors !== []) {
            return self::FAILURE;
        }

        if ($warnings !== [] && $this->option('strict')) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
