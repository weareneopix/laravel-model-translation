<?php

namespace WeAreNeopix\LaravelModelTranslation\Test\Unit;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use WeAreNeopix\LaravelModelTranslation\Test\Dependencies\Article;
use WeAreNeopix\LaravelModelTranslation\Test\Dependencies\ArticleWithSoftDeletes;
use WeAreNeopix\LaravelModelTranslation\Test\TestCase;
use WeAreNeopix\LaravelModelTranslation\Translation;

class JsonDriverTest extends TestCase
{
    /** @var \WeAreNeopix\LaravelModelTranslation\Drivers\JSONTranslationDriver */
    protected $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('translation.json.cache', true);

        /*
         * The Storage::fake() call has to be before the driver initialization call
         * Because initializing the driver will create a Storage disk instance and if
         * called before the fake it will be a genuine disk instance.
         */
        Storage::fake('translation')->put('meta/map.json', json_encode([]));
        $this->driver = Translation::driver('json');
    }

    /**
     * Creates and returns a ReflectionMethod object for the specified method.
     *
     * @param $methodName
     * @return \ReflectionMethod
     * @throws \ReflectionException
     */
    protected function getProtectedMethod($methodName)
    {
        $class = new \ReflectionClass($this->driver);
        $method = $class->getMethod($methodName);
        $method->setAccessible(true);
        return $method;
    }

    /**
     * Get the JSON parsed map.
     *
     * @return array
     * @throws \ReflectionException
     */
    protected function getMap(string $language)
    {
        $method = $this->getProtectedMethod('getLanguageModelMap');

        return $method->invokeArgs($this->driver, [$language]);
    }

    /**
     * Normalize the model identifier in order to prepare it for naming a directory after it.
     *
     * @param $modelIdentifier
     * @return string
     */
    protected function normalizeModelIdentifier($modelIdentifier)
    {
        $modelIdentifier = str_replace('\\', '_', $modelIdentifier);

        return Str::slug($modelIdentifier);
    }

    /**
     * Creates an article and the specified translations for it. Syncs the changes with the language-model map.
     *
     * @param array $languages
     * @param array $articleOverride
     * @return \WeAreNeopix\LaravelModelTranslation\Test\Dependencies\Article
     */
    protected function makeAndSyncArticle(array $languages, array $articleOverride = [])
    {
        $this->withoutJobs();

        $article = $this->makeArticleWithTranslations($languages, $articleOverride);

        foreach (array_keys($languages) as $language) {
            $this->driver->syncModelsForLanguage($language, $article);
            $this->assertContains((string) $article->id, $this->driver->getModelsAvailableInLanguage(Article::class, $language));
        }

        return $article;
    }

    /** @test */
    public function driver_can_get_the_models_available_in_a_language_from_the_map()
    {
        $getFromMap = $this->getProtectedMethod('getModelsAvailableInLanguageFromMap');
        $saveMap = $this->getProtectedMethod('saveMap');
        $map = [
            Article::class => [1, 2]
        ];
        $saveMap->invokeArgs($this->driver, [$map, 'en']);

        $returnedMap = $getFromMap->invokeArgs($this->driver, [Article::class, 'en']);

        $this->assertEquals([1, 2], $returnedMap);
    }

    /** @test */
    public function driver_can_parse_the_models_available_in_a_language()
    {
        $disk = Storage::disk('translation');
        $parseModels = $this->getProtectedMethod('parseModelsAvailableInLanguage');
        $modelDirectory = $this->normalizeModelIdentifier(Article::class);
        for ($i = 0; $i < 5; $i++) {
            $disk->put("{$modelDirectory}/{$i}/en.json", '');
        }

        $returnedModels = $parseModels->invokeArgs($this->driver, [Article::class, 'en']);

        $this->assertEquals([0, 1, 2, 3, 4], $returnedModels);
    }

    /**
     * @test
     * @testWith [false, [0, 1, 2, 3, 4]]
     *           [true, [10, 20, 30]]
     */
    public function driver_will_use_the_map_when_caching_is_enabled_and_parse_when_it_is_not($caching, $expectedModels)
    {
        $this->app['config']->set('translation.json.cache', $caching);

        $saveMap = $this->getProtectedMethod('saveMap');
        $map = [
            Article::class => [10, 20, 30]
        ];
        $saveMap->invokeArgs($this->driver, [$map, 'en']);

        $modelDirectory = $this->normalizeModelIdentifier(Article::class);
        $disk = Storage::disk('translation');
        for ($i = 0; $i < 5; $i++) {
            $disk->put("{$modelDirectory}/{$i}/en.json", '');
        }

        $returnedModels = $this->driver->getModelsAvailableInLanguage(Article::class, 'en');

        $this->assertEquals($expectedModels, $returnedModels);
    }

    /** @test */
    public function driver_can_return_the_language_model_map()
    {
        $this->makeAndSyncArticle([
            'en' => [
                'title' => 'Title in English'
            ]
        ]);

        $returnedMap = $this->getMap('en');
        $this->assertEquals([
            Article::class => [1]
        ], $returnedMap);
    }

    /** @test */
    public function driver_will_initialize_a_map_if_it_does_not_exist()
    {
        $disk = Storage::disk('translation');
        $disk->delete('meta/en.json');
        $disk->assertMissing('meta/en.json');
        $method = $this->getProtectedMethod('getLanguageModelMap');

        $method->invokeArgs($this->driver, ['en']);

        $disk->assertExists('meta/en.json');
    }

    /** @test */
    public function driver_can_save_the_language_model_map()
    {
        $method = $this->getProtectedMethod('saveMap');
        $disk = Storage::disk('translation');
        $disk->delete('meta/en.json');
        $newMap = [
            Article::class => [1]
        ];

        $method->invokeArgs($this->driver, [$newMap, 'en']);

        $disk->assertExists('meta/en.json');
        $this->assertEquals(json_encode($newMap), $disk->get('meta/en.json'));
    }


    /** @test */
    public function driver_can_add_a_model_instance_to_the_language_model_map()
    {
        $method = $this->getProtectedMethod('addModelToLanguageMap');
        $article = $this->makeArticleWithTranslations();

        $this->assertEmpty($this->driver->getModelsAvailableInLanguage(Article::class, 'en'));


        $method->invokeArgs($this->driver, [$article, 'en']);


        $this->assertEquals([1], $this->driver->getModelsAvailableInLanguage(Article::class, 'en'));
    }

    /** @test */
    public function driver_will_add_a_model_class_to_the_language_model_map_when_adding_an_instance()
    {
        $map = $this->getMap('en');
        $method = $this->getProtectedMethod('addModelToLanguageMap');
        $this->assertEmpty($map);
        $article = $this->makeArticleWithTranslations([]);

        $method->invokeArgs($this->driver, [$article, 'en']);

        $newMap = $this->getMap('en');
        $this->assertArrayHasKey(Article::class, $newMap);
    }

    /** @test */
    public function driver_will_add_a_langauge_to_the_map_when_adding_a_model_if_that_language_has_no_previous_translations()
   {
       $disk = Storage::disk('translation');
       $method = $this->getProtectedMethod('addModelToLanguageMap');
       $disk->delete('meta/en.json');
       $disk->assertMissing('meta/en.json');
       $article = Article::make();
       $article->id = 1;

       $method->invokeArgs($this->driver, [$article, 'en']);

       $disk->assertExists('meta/en.json');
       $this->assertEquals([
           Article::class => [1]
       ], $this->getMap('en'));
   }

    /** @test */
    public function existing_model_instances_in_map_will_remain_intact_upon_addition()
    {
        $method = $this->getProtectedMethod('addModelToLanguageMap');
        $existingModel = $this->makeAndSyncArticle([
            'en' => [
                'title' => 'Existing Model'
            ]
        ]);
        $newModel = $this->makeArticleWithTranslations([
            'en' => [
                'title' => 'New Model'
            ]
        ], [
            'id' => 2
        ]);
        $this->assertEquals([
            1
        ], $this->driver->getModelsAvailableInLanguage(Article::class, 'en'));

        $method->invokeArgs($this->driver, [$newModel, 'en']);

        $this->assertEquals([
            1, 2
        ], $this->driver->getModelsAvailableInLanguage(Article::class, 'en'));
    }

    /** @test */
    public function existing_model_classes_in_map_will_remain_intact_upon_addition()
    {
        $method = $this->getProtectedMethod('addModelToLanguageMap');
        $existingArticle = $this->makeAndSyncArticle([
            'en' => [
                'title' => 'Title in English'
            ]
        ]);
        $newArticle = ArticleWithSoftDeletes::make();
        $newArticle->id = 1;
        $this->assertEquals([
            Article::class => [1]
        ], $this->getMap('en'));

        $method->invokeArgs($this->driver, [$newArticle, 'en']);

        $this->assertEquals([
            Article::class => [1],
            ArticleWithSoftDeletes::class => [1]
        ], $this->getMap('en'));
    }

    /** @test */
    public function driver_will_not_store_duplicates_of_instance_identifiers_in_the_map()
    {
        $method = $this->getProtectedMethod('addModelToLanguageMap');
        $article = $this->makeAndSyncArticle([
            'en' => [
                'title' => 'Title in English'
            ]
        ]);
        $this->assertEquals([
            Article::class => [1]
        ], $this->getMap('en'));

        $method->invokeArgs($this->driver, [$article, 'en']);

        $this->assertEquals([
            Article::class => [1]
        ], $this->getMap('en'));
    }


    /** @test */
    public function driver_can_remove_a_model_instance_from_the_language_model_map()
    {
        $method = $this->getProtectedMethod('removeModelFromLanguageMap');
        $article = $this->makeAndSyncArticle([
            'en' => [
                'title' => 'Title in English'
            ]
        ]);


        $method->invokeArgs($this->driver, [$article, 'en']);


        $this->assertEmpty($this->driver->getModelsAvailableInLanguage(Article::class, 'en'));
    }

    /** @test */
    public function driver_will_remove_a_model_class_from_the_map_when_removing_its_last_instance()
    {
        $article = $this->makeAndSyncArticle([
            'en' => [
                'title' => 'Title in English'
            ]
        ]);
        $anotherModel = ArticleWithSoftDeletes::make([
           'id' => 1
        ]);
        $anotherModel->id = 1;
        $this->driver->storeTranslationsForModel($anotherModel, 'en', [
            'title' => 'Another article'
        ]);
        $this->driver->syncModelsForLanguage('en', $anotherModel);
        $map = $this->getMap('en');
        $this->assertEquals([
            Article::class => [1],
            ArticleWithSoftDeletes::class => [1]
        ], $map);
        $method = $this->getProtectedMethod('removeModelFromLanguageMap');

        $method->invokeArgs($this->driver, [$article, 'en']);

        $this->assertEquals([
            ArticleWithSoftDeletes::class => [1]
        ], $this->getMap('en'));
    }

    /** @test */
    public function driver_will_remove_a_language_from_the_map_if_it_has_no_more_translations_when_deleting_a_model_instance()
    {
        $articleInMap = [Article::class => [1]];
        $disk = Storage::disk('translation');
        $article = $this->makeAndSyncArticle([
            'en' => [
                'title' => 'Title in English'
            ],
            'es' => [
                'title' => 'Espagnolo titlo'
            ]
        ]);
        $this->assertEquals($articleInMap, $this->getMap('en'));
        $this->assertEquals($articleInMap, $this->getMap('es'));
        $disk->assertExists('meta/en.json');
        $disk->assertExists('meta/es.json');
        $method = $this->getProtectedMethod('removeModelFromLanguageMap');

        $method->invokeArgs($this->driver, [$article, 'es']);

        $disk->assertMissing('meta/es.json');
        $disk->assertExists('meta/en.json');
    }

    /** @test */
    public function other_model_instances_will_remain_intact_during_deletion_from_the_map()
    {
        $method = $this->getProtectedMethod('removeModelFromLanguageMap');
        $remainingLanguage = $this->makeAndSyncArticle([
            'en' => [
                'title' => 'Title in English'
            ]
        ]);
        $toBeDeleted = $this->makeAndSyncArticle([
            'en' => [
                'title' => 'Goodbye, sweet world'
            ]
        ], [
            'id' => 2
        ]);

        $method->invokeArgs($this->driver, [$toBeDeleted, 'en']);

        $this->assertEquals([
            Article::class => [1]
        ], $this->getMap('en'));
    }

    /** @test */
    public function other_model_classes_will_remain_intact_during_deletion_from_the_map()
    {
        $addToMap = $this->getProtectedMethod('addModelToLanguageMap');
        $removeFromMap = $this->getProtectedMethod('removeModelFromLanguageMap');
        $article = $this->makeAndSyncArticle([
            'en' => [
                'title' => 'Title in English'
            ]
        ]);
        $anotherModelArticle = ArticleWithSoftDeletes::make();
        $anotherModelArticle->id = 1;
        $addToMap->invokeArgs($this->driver, [$anotherModelArticle, 'en']);
        $this->assertEquals([
            Article::class => [1],
            ArticleWithSoftDeletes::class => [1]
        ], $this->getMap('en'));

        $removeFromMap->invokeArgs($this->driver, [$article, 'en']);

        $this->assertEquals([
            ArticleWithSoftDeletes::class => [1]
        ], $this->getMap('en'));
    }


    /** @test */
    public function driver_can_remove_a_model_instance_from_all_languages_in_the_language_model_map()
    {
        $article = $this->makeAndSyncArticle([
            'en' => [
                'title' => 'Title in English'
            ],
            'sr' => [
                'title' => 'Naslov na srpskom'
            ]
        ]);

        $this->driver->removeModelFromAllLanguages($article);

        $this->assertEmpty($this->driver->getModelsAvailableInLanguage(Article::class, 'en'));
        $this->assertEmpty($this->driver->getModelsAvailableInLanguage(Article::class, 'sr'));
    }

    /** @test */
    public function driver_will_remove_the_model_class_from_the_language_model_map_when_its_last_instance_is_removed()
    {
        $firstClassArticle = $this->makeAndSyncArticle([
            'en' => [
                'title' => 'Title in English'
            ]
        ]);
        $secondClassArticle = ArticleWithSoftDeletes::make();
        $secondClassArticle->id = 1;
        $this->driver->storeTranslationsForModel($secondClassArticle, 'en', ['title' => 'Title in English']);
        $this->driver->syncModelsForLanguage('en', $secondClassArticle);

        $this->assertEquals([
            Article::class => [1],
            ArticleWithSoftDeletes::class => [1]
        ], $this->getMap('en'));

        $this->driver->removeModelFromAllLanguages($secondClassArticle);

        $this->assertEquals([
            Article::class => [1]
        ], $this->getMap('en'));
    }

    /** @test */
    public function driver_will_remove_a_language_from_the_map_if_it_has_no_models_in_it_when_deleting_a_model_from_all_languages()
    {
        $disk = Storage::disk('translation');
        $articleToBeRemoved = $this->makeAndSyncArticle([
            'en' => [
                'title' => 'Title in English'
            ],
            'es' => [
                'title' => 'Espagnolo titlo'
            ]
        ]);
        $remainingArticle = $this->makeAndSyncArticle([
            'en' => [
                'Another title in English'
            ]
        ], ['id' => 2]);
        $this->assertEquals([
            Article::class => [1, 2]
        ], $this->getMap('en'));
        $this->assertEquals([
            Article::class => [1]
        ], $this->getMap('es'));
        $disk->assertExists('meta/en.json');
        $disk->assertExists('meta/es.json');

        $this->driver->removeModelFromAllLanguages($articleToBeRemoved);

        $this->assertEquals([
            Article::class => [2]
        ], $this->getMap('en'));
        $disk->assertMissing('meta/es.json');
    }

    /** @test */
    public function all_other_model_instances_in_all_languages_will_remain_intact_when_deleting_a_model_from_all_languages()
    {
        $remainingArticle = $this->makeAndSyncArticle([
            'en' => [
                'title' => 'Title in English'
            ],
            'sr' => [
                'title' => 'Naslov na srpskom'
            ]
        ]);
        $toBeDeleted = $this->makeAndSyncArticle([
            'en' => [
                'title' => 'Delete me'
            ],
            'sr' => [
                'title' => 'Obriši me'
            ]
        ], [
            'id' => 2
        ]);

        $this->driver->removeModelFromAllLanguages($toBeDeleted);

        $this->assertEquals([1], $this->driver->getModelsAvailableInLanguage(Article::class, 'en'));
        $this->assertEquals([1], $this->driver->getModelsAvailableInLanguage(Article::class, 'sr'));
    }

    /** @test */
    public function all_other_model_classes_in_all_languages_will_remain_intact_when_deleting_a_model_from_the_map()
    {
        $addToMap = $this->getProtectedMethod('addModelToLanguageMap');
        $toBeRemoved = $this->makeAndSyncArticle([
            'en' => [
                'title' => 'Title in English'
            ],
            'sr' => [
                'title' => 'Naslov na srpskom'
            ]
        ]);
        $anotherModelArticle = ArticleWithSoftDeletes::make();
        $anotherModelArticle->id = 1;
        $addToMap->invokeArgs($this->driver, [$anotherModelArticle, 'en']);
        $addToMap->invokeArgs($this->driver, [$anotherModelArticle, 'sr']);
        $this->assertEquals([
            Article::class => [1],
            ArticleWithSoftDeletes::class => [1]
        ], $this->getMap('en'));
        $this->assertEquals([
            Article::class => [1],
            ArticleWithSoftDeletes::class => [1]
        ], $this->getMap('sr'));

        $this->driver->removeModelFromAllLanguages($toBeRemoved);

        $this->assertEquals([
            ArticleWithSoftDeletes::class => [1]
        ], $this->getMap('en'));
        $this->assertEquals([
            ArticleWithSoftDeletes::class => [1]
        ], $this->getMap('sr'));
    }

    
    /** @test */
    public function driver_can_add_a_model_instance_to_map_when_syncing()
    {
        $this->withoutJobs();
        $modelInMap = [
            Article::class => [1]
        ];
        $article = $this->makeAndSyncArticle([
            'en' => [
                'title' => 'Title in English',
            ]
        ]);
        $initialMap = $this->getMap('en');
        $this->assertEquals($modelInMap, $initialMap);

        $this->driver->storeTranslationsForModel($article, 'es', [
            'title' => 'Espagnolo titlo'
        ]);
        $this->driver->syncModelsForLanguage('es', $article);

        $this->assertEquals($modelInMap, $this->getMap('en'));
        $this->assertEquals($modelInMap, $this->getMap('es'));
    }

    /** @test */
    public function driver_can_remove_a_model_instance_from_map_when_syncing()
    {
        $this->withoutJobs();
        $modelInMap = [
            Article::class => [1]
        ];
        $article = $this->makeAndSyncArticle([
            'en' => [
                'title' => 'Title in English',
            ],
            'es' => [
                'title' => 'Espagnolo titlo'
            ]
        ]);
        $this->assertEquals($modelInMap, $this->getMap('en'));
        $this->assertEquals($modelInMap, $this->getMap('es'));

        $this->driver->deleteLanguagesForModel($article, ['es']);
        $this->driver->syncModelsForLanguage('es', $article);

        $this->assertEquals($modelInMap, $this->getMap('en'));
        $this->assertEmpty($this->getMap('es'));
    }
}
