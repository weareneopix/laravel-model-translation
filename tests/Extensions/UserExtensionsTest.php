<?php

namespace WeAreNeopix\LaravelModelTranslation\Test\Extensions;

use Tests\TestCase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use WeAreNeopix\LaravelModelTranslation\Translation;
use WeAreNeopix\LaravelModelTranslation\Test\Dependencies\Article;

class UserExtensionsTest extends TestCase
{
    use RefreshDatabase;

    /** @var \WeAreNeopix\LaravelModelTranslation\Contracts\TranslationDriver */
    protected $driver;

    /** @var string */
    protected $databaseFlag = '_test_without_database_';

    /**
     * Parse the extensions that are to be tested from the command that has been ran.
     *
     * @return array
     */
    protected function parseExtensionsFromCommand()
    {
        global $argv;
        $index = 6;
        $extensions = [];
        do {
            $potentialExtension = $argv[$index];
            $index++;

            if ($potentialExtension === $this->databaseFlag) {
                continue;
            }

            $extensions[] = $potentialExtension;
        } while (isset($argv[$index]));

        return $extensions;
    }

    /**
     * Check if it is specified that the tests should not rely on a database.
     * If this is specified, we will override configuration to use the in-memory
     * database since we have to acknowledge the possibility that the user
     * does not have a database set up.
     *
     * @return void
     */
    public function disableDatabaseIfSpecified()
    {
        if ($this->shouldNotUseDatabase()) {
            $config = $this->app['config'];
            $config->set('database.default', 'testing');
            $config->set('database.connections.testing', [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ]);
        }
    }

    /**
     * Check whether the command that initiated the tests has the --no-database flag.
     *
     * @bool
     */
    public function shouldNotUseDatabase()
    {
        global $argv;

        return in_array($this->databaseFlag, $argv);
    }

    /**
     * We need to override this method in order to switch to the in-memory database before refreshing it.
     * This cannot be done from the constructor as it is called prior to application booting.
     *
     * @return array
     */
    protected function setUpTraits()
    {
        $this->disableDatabaseIfSpecified();

        return parent::setUpTraits();
    }

    protected function setUp()
    {
        parent::setUp();

        Storage::fake();
    }

    public function extensionsToTest()
    {
        $extensions = $this->parseExtensionsFromCommand();

        return array_map(function ($extension) {
            return [$extension];
        }, $extensions);
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

    /**
     * @test
     * @dataProvider extensionsToTest
     */
    public function driver_can_return_translations_for_model($driverName)
    {
        $this->driver = Translation::driver($driverName);
        $article = $this->makeArticleWithTranslations([
            'en' => [
                'title' => 'New Article Title',
                'body' => 'New Article Body',
            ],
        ]);

        $translations = $this->driver->getTranslationsForModel($article, 'en');

        $driverClass = get_class($this->driver);
        $message = "{$driverClass}::getTranslationsForModel() did not return the expected translations.";
        $this->assertEquals([
            'title' => 'New Article Title',
            'body' => 'New Article Body',
        ], $translations, $message);
    }

    /**
     * @test
     * @dataProvider extensionsToTest
     */
    public function driver_can_return_an_empty_array_when_the_requested_translations_do_not_exist($driverName)
    {
        $this->driver = Translation::driver($driverName);
        $article = $this->makeArticleWithTranslations();

        $translations = $this->driver->getTranslationsForModel($article, 'en');

        $driverClass = get_class($this->driver);
        $message = "{$driverClass}::getTranslationsForModel() should return an empty array when no translations are available.";
        $this->assertEmpty($translations, $message);
    }

    /**
     * @test
     * @dataProvider extensionsToTest
     */
    public function driver_can_return_translations_for_multiple_models($driverName)
    {
        $this->driver = Translation::driver($driverName);
        $articles = collect([
            Article::make(['id' => 1]),
            Article::make(['id' => 2]),
        ]);
        $articles->each(function (Article $article) {
            $this->driver->storeTranslationsForModel($article, 'en', [
                'title' => "New Article Title {$article->id}",
            ]);
        });

        $translations = $this->driver->getTranslationsForModels($articles, 'en');

        $expectedOutput = [
            1 => [
                'title' => 'New Article Title 1',
            ],
            2 => [
                'title' => 'New Article Title 2',
            ],
        ];
        $driverName = get_class($this->driver);
        $message = "{$driverName}::getTranslationsForModells() should return an array where the keyes are model primary keyes and the values are associative arrays of translations";
        $this->assertEquals($expectedOutput, $translations->toArray(), $message);
    }

    /**
     * @test
     * @dataProvider extensionsToTest
     */
    public function driver_can_return_a_collection_of_empty_arrays_when_no_translations_for_any_of_the_models_are_available($driverName)
    {
        $this->driver = Translation::driver($driverName);
        $articles = collect([
            Article::make(['id' => 1]),
            Article::make(['id' => 2]),
        ]);

        $translations = $this->driver->getTranslationsForModels($articles, 'en');

        $translations->each(function (array $translationsForModel) {
            $this->assertEmpty($translationsForModel);
        });
    }

    /**
     * @test
     * @dataProvider extensionsToTest
     */
    public function driver_can_put_translations_for_the_model_thus_overriding_any_previous_translations($driverName)
    {
        $this->driver = Translation::driver($driverName);
        $article = $this->makeArticleWithTranslations([
            'en' => [
                'title' => 'Old Title',
                'description' => 'I will soon disappear',
            ],
        ]);

        $this->driver->putTranslationsForModel($article, 'en', [
            'title' => 'New Title',
        ]);

        $translations = $this->driver->getTranslationsForModel($article, 'en');
        $this->assertEquals([
            'title' => 'New Title',
        ], $translations);
    }

    /**
     * @test
     * @dataProvider extensionsToTest
     */
    public function driver_can_patch_the_requested_translations_without_affecting_any_others($driverName)
    {
        $this->driver = Translation::driver($driverName);
        $article = $this->makeArticleWithTranslations([
            'en' => [
                'title' => 'Old Title',
                'description' => "I'm not leaving!",
            ],
        ]);

        $this->driver->patchTranslationsForModel($article, 'en', [
            'title' => 'New Title',
        ]);

        $translations = $this->driver->getTranslationsForModel($article, 'en');
        $this->assertEquals([
            'title' => 'New Title',
            'description' => "I'm not leaving!",
        ], $translations);
    }

    /**
     * @test
     * @dataProvider extensionsToTest
     */
    public function driver_can_delete_all_translations_for_a_model($driverName)
    {
        $this->driver = Translation::driver($driverName);
        $article = $this->makeArticleWithTranslations([
            'en' => [
                'title' => 'Goodbye my lover, goodbye my friend!',
            ],
            'es' => [
                'title' => 'Adios mi amore, adios mi amigo!',
            ],
        ]);

        $this->driver->deleteAllTranslationsForModel($article);

        $translationsInEnglish = $this->driver->getTranslationsForModel($article, 'en');
        $this->assertEmpty($translationsInEnglish);

        $translationsInSpanish = $this->driver->getTranslationsForModel($article, 'es');
        $this->assertEmpty($translationsInSpanish);
    }

    /**
     * @test
     * @dataProvider extensionsToTest
     */
    public function driver_can_delete_all_translations_for_a_model_in_a_specific_language($driverName)
    {
        $this->driver = Translation::driver($driverName);
        $article = $this->makeArticleWithTranslations([
            'en' => [
                'title' => "I'm staying right here!",
            ],
            'de' => [
                'title' => 'Auf wieder sehen, meine liebe freund!',
                'description' => 'Ich spreche ein bischen Deutsch',
            ],
            'rs' =>[
                'title' => 'Dođavola sve, dođavola sa mnom',
                'description' => 'Odlazim a volim te',
            ],
        ]);

        $this->driver->deleteLanguagesForModel($article, ['de', 'rs']);

        $translationsInGerman = $this->driver->getTranslationsForModel($article, 'de');
        $this->assertEmpty($translationsInGerman);

        $translationsInSerbian = $this->driver->getTranslationsForModel($article, 'rs');
        $this->assertEmpty($translationsInSerbian);

        $translationsInEnglish = $this->driver->getTranslationsForModel($article, 'en');
        $this->assertEquals([
            'title' => "I'm staying right here!",
        ], $translationsInEnglish);
    }

    /**
     * @test
     * @dataProvider extensionsToTest
     */
    public function driver_can_delete_specific_translations_for_a_model_in_all_languages($driverName)
    {
        $this->driver = Translation::driver($driverName);
        $article = $this->makeArticleWithTranslations([
            'en' => [
                'title' => 'I will survive!',
                'description' => 'I will not.',
            ],
            'de' => [
                'title' => 'Hallo, freund!',
                'description' => 'Auf wieder sehen!',
            ],
        ]);

        $this->driver->deleteAttributesForModel($article, ['description']);

        $translationsInEnglish = $this->driver->getTranslationsForModel($article, 'en');
        $this->assertEquals([
            'title' => 'I will survive!',
        ], $translationsInEnglish);

        $translationsInGerman = $this->driver->getTranslationsForModel($article, 'de');
        $this->assertEquals([
            'title' => 'Hallo, freund!',
        ], $translationsInGerman);
    }

    /**
     * @test
     * @dataProvider extensionsToTest
     */
    public function driver_can_delete_specific_translations_for_a_model_in_a_specific_language($driverName)
    {
        $this->driver = Translation::driver($driverName);
        $article = $this->makeArticleWithTranslations([
            'en' => [
                'title' => "Can't touch this.",
                'description' => "And this is a beat you can't touch.",
            ],
            'de' => [
                'title' => 'Neunundneunzig Nuftballons',
                'description' => 'Auf ihrem Weg zum Horizont',
            ],
        ]);

        $this->driver->deleteAttributesForModel($article, ['description'], 'de');

        $translationsInEnglish = $this->driver->getTranslationsForModel($article, 'en');
        $this->assertEquals([
            'title' => "Can't touch this.",
            'description' => "And this is a beat you can't touch.",
        ], $translationsInEnglish);

        $translationsInGerman = $this->driver->getTranslationsForModel($article, 'de');
        $this->assertEquals([
            'title' => 'Neunundneunzig Nuftballons',
        ], $translationsInGerman);
    }

    /**
     * @test
     * @dataProvider extensionsToTest
     */
    public function driver_can_return_an_array_of_all_the_available_languages_for_a_model($driverName)
    {
        $this->driver = Translation::driver($driverName);
        $article = $this->makeArticleWithTranslations([
            'en' => [
                'title' => 'New Article',
            ],
            'es' => [
                'title' => 'Articulo Nuevo ',
            ],
        ]);

        $availableLanguages = $this->driver->getAvailableLanguagesForModel($article);

        $this->assertEquals([
            'en', 'es',
        ], $availableLanguages);
    }

    /**
     * @test
     * @dataProvider extensionsToTest
     */
    public function deleting_all_language_translations_will_delete_the_language_from_the_list_of_available_languages($driverName)
    {
        $this->driver = Translation::driver($driverName);
        $article = $this->makeArticleWithTranslations([
            'en' => [
                'title' => 'New Article',
            ],
            'es' => [
                'title' => 'Articulo Nuevo',
            ],
        ]);

        $this->driver->deleteLanguagesForModel($article, ['en']);

        $availableLangauges = $this->driver->getAvailableLanguagesForModel($article);
        $this->assertEquals(['es'], $availableLangauges);
    }

    /**
     * @test
     * @dataProvider extensionsToTest
     */
    public function language_will_be_present_in_all_languages_list_as_long_as_it_has_at_least_a_single_translation($driverName)
    {
        $this->driver = Translation::driver($driverName);
        $article = $this->makeArticleWithTranslations([
            'en' => [
                'title' => 'New Article',
                'body' => 'My time is running out.',
            ],
            'es' => [
                'title' => 'Articlo Nuevo',
            ],
        ]);

        $this->driver->deleteAttributesForModel($article, ['body'], 'en');

        $availableLanguages = $this->driver->getAvailableLanguagesForModel($article);
        $this->assertEquals([
            'en', 'es',
        ], $availableLanguages);
    }

    /**
     * @test
     * @dataProvider extensionsToTest
     */
    public function driver_will_return_an_empty_array_if_no_languages_are_available_for_a_model($driverName)
    {
        $this->driver = Translation::driver($driverName);
        $article = Article::make(['id' => 1]);

        $availableLanguages = $this->driver->getAvailableLanguagesForModel($article);

        $this->assertEquals([], $availableLanguages);
    }

    /**
     * @test
     * @dataProvider extensionsToTest
     */
    public function driver_can_return_a_list_of_all_the_models_available_in_a_particular_language($driverName)
    {
        $this->driver = Translation::driver($driverName);
        $articlesInList = collect([
            $this->makeArticleWithTranslations([
                'en' => [
                    'title' => 'I exist',
                ],
            ], ['id' => 1]),
            $this->makeArticleWithTranslations([
                'en' => [
                    'title' => 'Me too',
                ],
            ], ['id' => 2]),
        ]);
        $articleNotInList = $this->makeArticleWithTranslations([
            'fr' => [
                'title' => "J' existe",
            ],
        ], ['id' => 3]);

        $articlesInLanguage = $this->driver->getModelsAvailableInLanguage(Article::class, 'en');

        $this->assertEquals([
            1, 2,
        ], $articlesInLanguage);
    }

    /**
     * @test
     * @dataProvider extensionsToTest
     */
    public function driver_will_return_an_empty_array_if_no_models_are_available_for_the_selected_language($driverName)
    {
        $this->driver = Translation::driver($driverName);

        $models = $this->driver->getModelsAvailableInLanguage(Article::class, 'en');

        $this->assertEquals([], $models);
    }
}
