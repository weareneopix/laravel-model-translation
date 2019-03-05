<?php

namespace WeAreNeopix\LaravelModelTranslation\Test;

use WeAreNeopix\LaravelModelTranslation\Translation;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use WeAreNeopix\LaravelModelTranslation\ModelTranslationServiceProvider;

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
