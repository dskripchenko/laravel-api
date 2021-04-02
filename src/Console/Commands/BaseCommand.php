<?php

namespace Dskripchenko\LaravelApi\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Class BaseCommand
 * @package Dskripchenko\LaravelApi\Console\Commands
 */
class BaseCommand extends Command
{
    /**
     * @param $question
     * @param $rules
     * @param null $default
     * @return mixed
     */
    protected function askValid(string $question, array $rules = [], $default = null)
    {
        do {
            $value = $this->ask($question, $default);
            $errorMessage = $this->validateInput($rules, $value);
            if ($errorMessage) {
                $this->error($errorMessage);
            }
        } while ($errorMessage);

        return $value;
    }

    /**
     * @param $rules
     * @param $value
     * @return string|null
     */
    protected function validateInput($rules, $value): ?string
    {
        $validator = Validator::make([
            'field' => $value
        ], [
            'field' => $rules
        ]);

        return $validator->fails()
            ? $validator->errors()->first('field')
            : null;
    }
}
