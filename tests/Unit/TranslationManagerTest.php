<?php

namespace MisaNeopix\LaravelModelTranslation\Test\Unit;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use MisaNeopix\LaravelModelTranslation\Translation;
use MisaNeopix\LaravelModelTranslation\Test\TestCase;
use MisaNeopix\LaravelModelTranslation\TranslationManager;
use MisaNeopix\LaravelModelTranslation\Contracts\TranslationDriver;
use MisaNeopix\LaravelModelTranslation\Drivers\JSONTranslationDriver;
use MisaNeopix\LaravelModelTranslation\Drivers\ArrayTranslationDriver;
use MisaNeopix\LaravelModelTranslation\Exceptions\NoDefaultDriverException;
use MisaNeopix\LaravelModelTranslation\Drivers\MySQL\MySQLTranslationDriver;
use MisaNeopix\LaravelModelTranslation\Exceptions\InvalidTranslationDriverException;

class TranslationManagerTest extends TestCase
{
    /** @var TranslationManager */
    protected $manager;

    public function driversWithAbstracts()
    {
        return [
            ['json', JSONTranslationDriver::class],
            ['mysql', MySQLTranslationDriver::class],
            ['array', ArrayTranslationDriver::class],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = $this->app['translation'];
    }

    protected function getValidExtension()
    {
        return new class implements TranslationDriver {
            public function storeTranslationsForModel(Model $model, string $language, array $translations): bool
            {
            }

            public function getTranslationsForModel(Model $model, string $language): array
            {
            }

            public function getTranslationsForModels(Collection $models, string $language): Collection
            {
            }

            public function getAvailableLanguagesForModel(Model $model): array
            {
            }

            public function getModelsAvailableInLanguage(string $modelIdentifier, string $language): array
            {
            }

            public function putTranslationsForModel(Model $model, string $language, array $translations): bool
            {
            }

            public function patchTranslationsForModel(Model $model, string $language, array $translations): bool
            {
            }

            public function deleteAllTranslationsForModel(Model $model): bool
            {
            }

            public function deleteLanguagesForModel(Model $model, array $languages): bool
            {
            }

            public function deleteAttributesForModel(Model $model, array $attributes, string $language = null): bool
            {
            }
        };
    }

    /** @test */
    public function translation_facade_will_proxy_calls_to_the_translation_manager()
    {
        $mock = \Mockery::mock(TranslationManager::class);
        $expectation = $mock->shouldReceive('proxiedMethod')->with('first_arg', 'second_arg');
        $this->app->instance('translation', $mock);

        Translation::proxiedMethod('first_arg', 'second_arg');

        $expectation->verify();
    }

    /** @test */
    public function translation_manager_can_create_a_json_driver()
    {
        $jsonDriver = $this->manager->createJsonDriver();

        $this->assertTrue($jsonDriver instanceof JSONTranslationDriver);
    }

    /** @test */
    public function translation_manager_can_create_a_mysql_driver()
    {
        $mysqlDriver = $this->manager->createMysqlDriver();

        $this->assertTrue($mysqlDriver instanceof MySQLTranslationDriver);
    }

    /** @test */
    public function translation_manager_can_create_an_array_driver()
    {
        $arrayDriver = $this->manager->createArrayDriver();

        $this->assertTrue($arrayDriver instanceof ArrayTranslationDriver);
    }

    /** @test */
    public function translation_driver_will_throw_an_exception_if_no_default_driver_has_been_set()
    {
        $this->app['config']->set('translation.driver', null);
        $this->expectException(NoDefaultDriverException::class);

        $this->manager->getDefaultDriver();
    }

    /**
     * @test
     * @dataProvider driversWithAbstracts
     */
    public function user_can_change_the_specified_default_driver($abstract, $driverClass)
    {
        $this->manager->setDefaultDriver($abstract);

        $this->assertTrue($this->manager->driver() instanceof $driverClass);
    }

    /** @test */
    public function translation_manager_can_be_extended_with_new_drivers()
    {
        /*
         * Create an anonymous class as a placeholder for the extended driver
         * Register this class with the TranslationManager
         */
        $driverExtension = $this->getValidExtension();
        $this->manager->extend('new_driver', function ($app) use ($driverExtension) {
            return new $driverExtension;
        });

        /*
         * Assert that the new class has been returned as the driver.
         */
        $this->assertTrue($this->manager->driver('new_driver') instanceof $driverExtension);
    }

    /** @test */
    public function driver_extensions_can_be_used_as_default_drivers()
    {
        $driverExtension = $this->getValidExtension();
        $this->manager->extend('new_default', function ($app) use ($driverExtension) {
            return new $driverExtension;
        });

        $this->manager->setDefaultDriver('new_default');

        $this->assertTrue($this->manager->driver() instanceof $driverExtension);
    }

    /** @test */
    public function translation_manager_will_use_array_driver_when_faking()
    {
        Translation::fake();

        $this->assertTrue($this->manager->driver() instanceof ArrayTranslationDriver);
    }

    /** @test */
    public function translation_manager_will_throw_an_error_when_a_driver_does_not_implement_the_contract()
    {
        $extension = new class {
        };
        $this->manager->extend('invalid_driver', function () use ($extension) {
            return new $extension;
        });
        $this->expectException(InvalidTranslationDriverException::class);

        $this->manager->driver('invalid_driver');
    }

    /** @test */
    public function translation_manager_can_return_a_list_of_all_the_registered_extensions()
    {
        $this->manager->extend('new_driver', function () {
            $extension = $this->getValidExtension();

            return new $extension;
        });

        $extensions = $this->manager->getRegisteredExtensionNames();

        $this->assertEquals($extensions, [
            'new_driver',
        ]);
    }

    /** @test */
    public function translation_manager_can_return_a_list_of_all_the_available_drivers()
    {
        $this->manager->extend('new_driver', function () {
            $extension = $this->getValidExtension();

            return new $extension;
        });

        $drivers = $this->manager->getAvailableDrivers();

        $this->assertEquals($drivers, [
            'json', 'mysql', 'array', 'new_driver',
        ]);
    }
}
