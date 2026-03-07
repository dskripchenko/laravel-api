<?php

declare(strict_types=1);

use Dskripchenko\LaravelApi\Console\Commands\BaseCommand;

it('validateInput returns null for valid input', function () {
    $command = new class extends BaseCommand {
        protected $signature = 'test:cmd';

        public function handle(): void {}

        public function testValidate($rules, $value): ?string
        {
            return $this->validateInput($rules, $value);
        }
    };

    $this->app->make(\Illuminate\Contracts\Console\Kernel::class);
    $result = $command->testValidate(['required', 'string'], 'valid value');
    expect($result)->toBeNull();
});

it('validateInput returns error for invalid input', function () {
    $command = new class extends BaseCommand {
        protected $signature = 'test:cmd';

        public function handle(): void {}

        public function testValidate($rules, $value): ?string
        {
            return $this->validateInput($rules, $value);
        }
    };

    $result = $command->testValidate(['required', 'email'], 'not-an-email');
    expect($result)->not->toBeNull();
    expect($result)->toBeString();
});

it('validateInput handles complex rules', function () {
    $command = new class extends BaseCommand {
        protected $signature = 'test:cmd';

        public function handle(): void {}

        public function testValidate($rules, $value): ?string
        {
            return $this->validateInput($rules, $value);
        }
    };

    $result = $command->testValidate(['required', 'integer', 'min:1'], '0');
    expect($result)->not->toBeNull();

    $result2 = $command->testValidate(['required', 'integer', 'min:1'], '5');
    expect($result2)->toBeNull();
});

it('validateInput returns null for empty rules', function () {
    $command = new class extends BaseCommand {
        protected $signature = 'test:cmd';

        public function handle(): void {}

        public function testValidate($rules, $value): ?string
        {
            return $this->validateInput($rules, $value);
        }
    };

    $result = $command->testValidate([], null);
    expect($result)->toBeNull();
});
