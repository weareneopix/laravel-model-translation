<?php

namespace WeAreNeopix\LaravelModelTranslation\Test\Unit;

use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use WeAreNeopix\LaravelModelTranslation\Translation;
use WeAreNeopix\LaravelModelTranslation\Test\TestCase;
use WeAreNeopix\LaravelModelTranslation\Test\Dependencies\Article;
use WeAreNeopix\LaravelModelTranslation\Contracts\TranslationDriver;

class TranslationDriversTest extends TestCase
{
    use RefreshDatabase;

    public function availableDrivers()
    {
        return [
            ['json'],
            ['mysql'],
            ['array'],
        ];
    }

    /** @var TranslationDriver */
    protected $driver;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('translation');
        $this->loadMigrationsFrom(__DIR__.'/../Dependencies');
        $this->loadMigrationsFrom(__DIR__.'/../../migrations');
    }

    /**
     * @test
     * @dataProvider availableDrivers
     */
    public function driver_can_return_translations_for_model($driverName)
    {
        $this->withoutJobs();
        $this->driver = Translation::driver($driverName);
        $article = $this->makeArticleWithTranslations([
            'en' => [
                'title' => 'New Article Title',
                'body' => 'New Article Body',
            ],
        ]);

        $translations = $this->driver->getTranslationsForModel($article, 'en');

        $this->assertEquals([
            'title' => 'New Article Title',
            'body' => 'New Article Body',
        ], $translations);
    }

    /**
     * @test
     * @dataProvider availableDrivers
     */
    public function driver_can_return_an_empty_array_when_the_requested_translations_do_not_exist($driverName)
    {
        $this->driver = Translation::driver($driverName);
        $article = $this->makeArticleWithTranslations();

        $translations = $this->driver->getTranslationsForModel($article, 'en');

        $this->assertEmpty($translations);
    }

    /**
     * @test
     * @dataProvider availableDrivers
     */
    public function driver_can_return_translations_for_multiple_models($driverName)
    {
        $this->withoutJobs();
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

        $articles->each(function (Article $article) use ($translations) {
            $translationForArticle = $translations->get($article->getInstanceIdentifier());
            $this->assertEquals("New Article Title {$article->id}", $translationForArticle['title']);
        });
    }

    /**
     * @test
     * @dataProvider availableDrivers
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
     * @dataProvider availableDrivers
     */
    public function driver_can_put_translations_for_the_model_thus_overriding_any_previous_translations($driverName)
    {
        $this->withoutJobs();
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
     * @dataProvider availableDrivers
     */
    public function driver_can_patch_the_requested_translations_without_affecting_any_others($driverName)
    {
        $this->withoutJobs();
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
     * @dataProvider availableDrivers
     */
    public function driver_can_delete_all_translations_for_a_model($driverName)
    {
        $this->withoutJobs();
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
     * @dataProvider availableDrivers
     */
    public function driver_can_delete_all_translations_for_a_model_in_specific_languages($driverName)
    {
        $this->withoutJobs();
        $this->driver = Translation::driver($driverName);
        $article = $this->makeArticleWithTranslations([
            'en' => [
                'title' => "I'm staying right here!",
            ],
            'de' => [
                'title' => 'Auf wieder sehen, meine liebe freund!',
                'description' => 'Ich spreche ein bischen Deutsch',
            ],
            'rs' => [
                'title' => 'Dođavola sve, dođavola sa mnom',
                'description' => 'Odlazim, a volim te',
            ],
        ]);

        $this->driver->deleteLanguagesForModel($article, ['de', 'rs']);

        $translationsInGerman = $this->driver->getTranslationsForModel($article, 'de');
        $this->assertEquals([], $translationsInGerman);

        $translationsInSerbian = $this->driver->getTranslationsForModel($article, 'rs');
        $this->assertEquals([], $translationsInSerbian);

        $translationsInEnglish = $this->driver->getTranslationsForModel($article, 'en');
        $this->assertEquals([
            'title' => "I'm staying right here!",
        ], $translationsInEnglish);
    }

    /**
     * @test
     * @dataProvider availableDrivers
     */
    public function driver_can_delete_specific_translations_for_a_model_in_all_languages($driverName)
    {
        $this->withoutJobs();
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
     * @dataProvider availableDrivers
     */
    public function driver_can_delete_specific_translations_for_a_model_in_a_specific_language($driverName)
    {
        $this->withoutJobs();
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
     * @dataProvider availableDrivers
     */
    public function deleting_all_translations_for_a_language_will_remove_the_language_from_the_list_of_available_languages($driverName)
    {
        $this->withoutJobs();
        $this->driver = Translation::driver($driverName);
        $article = $this->makeArticleWithTranslations([
            'en' => [
                'title' => 'Title in English',
                'body' => 'Body in English',
                'description' => 'Description in English'
            ]
        ]);
        $this->assertEquals(['en'], $this->driver->getAvailableLanguagesForModel($article));

        $this->driver->deleteAttributesForModel($article, ['title', 'body', 'description'], 'en');

        $this->assertEmpty($this->driver->getAvailableLanguagesForModel($article));
    }

    /**
     * @test
     * @dataProvider availableDrivers
     */
    public function driver_can_return_an_array_of_all_the_available_languages_for_a_model($driverName)
    {
        $this->withoutJobs();
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
     * @dataProvider availableDrivers
     */
    public function deleting_all_language_translations_will_delete_the_language_from_the_list_of_available_languages($driverName)
    {
        $this->withoutJobs();
        $this->driver = Translation::driver($driverName);
        $article = $this->makeArticleWithTranslations([
            'en' => [
                'title' => 'New Article',
            ],
            'es' => [
                'title' => 'Articulo Nuevo',
            ],
        ]);

        $this->driver->deleteLanguagesForModel($article, ['ru', 'en']);

        $availableLangauges = $this->driver->getAvailableLanguagesForModel($article);
        $this->assertEquals(['es'], $availableLangauges);
    }

    /**
     * @test
     * @dataProvider availableDrivers
     */
    public function language_will_be_present_in_all_languages_list_as_long_as_it_has_at_least_a_single_translation($driverName)
    {
        $this->withoutJobs();
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
     * @dataProvider availableDrivers
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
     * @dataProvider availableDrivers
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
     * @dataProvider availableDrivers
     */
    public function driver_will_return_an_empty_array_if_no_models_are_available_for_the_selected_language($driverName)
    {
        $this->driver = Translation::driver($driverName);

        $models = $this->driver->getModelsAvailableInLanguage(Article::class, 'en');

        $this->assertEquals([], $models);
    }
}
