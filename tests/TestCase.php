<?php

namespace MisaNeopix\LaravelModelTranslation\Test;

use MisaNeopix\LaravelModelTranslation\Translation;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use MisaNeopix\LaravelModelTranslation\ModelTranslationServiceProvider;

class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app)
    {
        return [ModelTranslationServiceProvider::class];
    }

    protected function getPackageAliases($app)
    {
        return ['Translation' => Translation::class];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testing');
    }
}
