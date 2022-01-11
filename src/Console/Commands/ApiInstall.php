<?php

namespace Dskripchenko\LaravelApi\Console\Commands;

use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Support\Arr;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Artisan;

/**
 * Class ApiInstall
 * @package Dskripchenko\LaravelApi\Console\Commands
 */
class ApiInstall extends BaseCommand
{
    protected $signature = 'api:install';

    protected $description = 'Начальная настройка Api';

    public function handle()
    {
        $this->onBeginSetup();
        Artisan::call('storage:link');

        copy(base_path('.env.example'), base_path('.env'));
        Artisan::call('key:generate');
        $this->fillEnvironment();
        Artisan::call('config:cache');
        $this->reloadEnvironment();

        Artisan::call('migrate');
        $this->onEndSetup();
    }

    protected function getEnvConfig(): array
    {
        return [
            'Параметры подключения к базе данных' => [
                '{{DB_CONNECTION}}' => [
                    'name' => 'Драйвер',
                    'default' => 'mysql',
                    'rules' => [
                        'in:mysql,pgsql'
                    ]
                ],
                '{{DB_HOST}}' => [
                    'name' => 'Хост',
                    'default' => '127.0.0.1',
                ],
                '{{DB_PORT}}' => [
                    'name' => 'Порт',
                    'default' => 3306,
                    'rules' => [
                        'integer'
                    ]
                ],
                '{{DB_DATABASE}}' => [
                    'name' => 'База данных',
                    'rules' => [
                        'required'
                    ]
                ],
                '{{DB_USERNAME}}' => [
                    'name' => 'Пользователь',
                    'rules' => [
                        'required'
                    ]
                ],
                '{{DB_PASSWORD}}' => [
                    'name' => 'Пароль',
                ],
                '{{DB_SCHEMA}}' => [
                    'name' => 'Схема',
                    'default' => 'public',
                ],
            ],
        ];
    }

    protected function fillEnvironment(): void
    {
        $config      = $this->getEnvConfig();
        $envFilePath = base_path('.env');
        $env = file_get_contents($envFilePath);
        foreach ($config as $section => $environments) {
            $this->alert($section);
            foreach ($environments as $key => $options) {
                $question = Arr::get($options, 'name', $key);
                $rules    = Arr::get($options, 'rules', []);
                $default  = Arr::get($options, 'default');
                $value    = $this->askValid($question, $rules, $default);
                $env      = str_replace($key, $value, $env);
            }
        }
        file_put_contents($envFilePath, $env);
    }

    protected function reloadEnvironment(): void
    {
        // Сбрасываем текущий экземпляр конфигурационной фабрики
        Env::enablePutenv();

        // Полный релоад конфигурации текущего аппликейшена
        /**
         * @var LoadConfiguration $configurationLoader
         */
        $configurationLoader = $this->laravel->make(LoadConfiguration::class);
        $configurationLoader->bootstrap($this->laravel);
    }

    protected function onBeginSetup(): void
    {
        //for override
    }

    protected function onEndSetup(): void
    {
        //for override
    }
}
