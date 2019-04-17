<?php

namespace WeAreNeopix\LaravelModelTranslation\Test;

use WeAreNeopix\LaravelModelTranslation\Test\Dependencies\Article;
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

    /**
     * Create an Article model with translations.
     *
     * @param array $translations
     * @param array $articleOverride
     * @return Article
     */
    protected function makeArticleWithTranslations(array $translations = [], array $articleOverride = [])
    {
        $article = Article::make(array_merge([
            'id' => 1,
        ], $articleOverride));

        foreach ($translations as $language => $translation) {
            $this->driver->storeTranslationsForModel($article, $language, $translation);
        }

        return $article;
    }
}
