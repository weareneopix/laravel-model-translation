<?php

namespace WeAreNeopix\LaravelModelTranslation\Test\Unit;

use Illuminate\Support\Facades\Storage;
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
    protected function getMap()
    {
        $method = $this->getProtectedMethod('getLanguageModelMap');

        return $method->invoke($this->driver);
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
            $this->assertContains($article->id, $this->driver->getModelsAvailableInLanguage(Article::class, $language));
        }

        return $article;
    }

    /** @test */
    public function driver_can_return_the_language_model_map()
    {
        $this->makeAndSyncArticle([
            'en' => [
                'title' => 'Title in English'
            ]
        ]);

        $returnedMap = $this->getMap();
        $this->assertEquals([
            'en' => [
                Article::class => [1]
            ]
        ], $returnedMap);
    }

    /** @test */
    public function driver_will_initialize_a_map_if_it_does_not_exist()
    {
        $disk = Storage::disk('translation');
        $disk->delete('meta/map.json');
        $disk->assertMissing('meta/map.json');
        $method = $this->getProtectedMethod('getLanguageModelMap');

        $method->invoke($this->driver);

        $disk->assertExists('meta/map.json');
    }

    /** @test */
    public function driver_can_save_the_language_model_map()
    {
        $method = $this->getProtectedMethod('saveMap');
        $disk = Storage::disk('translation');
        $disk->delete('meta/map.json');
        $newMap = [
            'en' => [
                Article::class => [
                    1
                ]
            ]
        ];

        $method->invokeArgs($this->driver, [$newMap]);

        $disk->assertExists('meta/map.json');
        $this->assertEquals(json_encode($newMap), $disk->get('meta/map.json'));
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
    public function driver_can_add_a_model_class_to_the_language_model_map_when_adding_an_instance()
    {
        $map = $this->getMap();
        $method = $this->getProtectedMethod('addModelToLanguageMap');
        $this->assertEmpty($map);
        $article = $this->makeArticleWithTranslations([]);

        $method->invokeArgs($this->driver, [$article, 'en']);

        $newMap = $this->getMap();
        $this->assertArrayHasKey(Article::class, $newMap['en']);
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
    public function driver_will_not_store_duplicates_of_instance_identifiers_in_the_map()
    {
        $method = $this->getProtectedMethod('addModelToLanguageMap');
        $article = $this->makeAndSyncArticle([
            'en' => [
                'title' => 'Title in English'
            ]
        ]);
        $this->assertEquals([
            'en' => [
                Article::class => [1]
            ]
        ], $this->getMap());

        $method->invokeArgs($this->driver, [$article, 'en']);

        $this->assertEquals([
            'en' => [
                Article::class => [1]
            ]
        ], $this->getMap());
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
    public function driver_can_remove_a_model_class_from_the_map_when_removing_its_last_instance()
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
        $map = $this->getMap();
        $this->assertEquals([
            'en' => [
                Article::class => [1],
                ArticleWithSoftDeletes::class => [1]
            ],
        ], $map);
        $method = $this->getProtectedMethod('removeModelFromLanguageMap');

        $method->invokeArgs($this->driver, [$article, 'en']);

        $this->assertEquals([
            'en' => [
                ArticleWithSoftDeletes::class => [1]
            ]
        ], $this->getMap());
    }

    /** @test */
    public function driver_will_remove_a_language_from_the_map_if_it_has_no_more_translations_when_deleting_a_model_instance()
    {
        $article = $this->makeAndSyncArticle([
            'en' => [
                'title' => 'Title in English'
            ],
            'es' => [
                'title' => 'Espagnolo titlo'
            ]
        ]);
        $this->assertEquals([
            'en' => [
                Article::class => [1]
            ],
            'es' => [
                Article::class => [1]
            ]
        ], $this->getMap());
        $method = $this->getProtectedMethod('removeModelFromLanguageMap');

        $method->invokeArgs($this->driver, [$article, 'es']);

        $this->assertArrayNotHasKey('es', $this->getMap());
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
            'en' => [
                Article::class => [1]
            ]
        ], $this->getMap());
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
                'title' => 'New title in English'
            ]
        ], ['id' => 2]);
        $this->assertEquals([
            'en' => [
                Article::class => [1, 2]
            ],
            'es' => [
                Article::class => [1]
            ]
        ], $this->getMap());

        $this->driver->removeModelFromAllLanguages($articleToBeRemoved);

        $this->assertEquals([
            'en' => [
                Article::class => [2]
            ],
        ], $this->getMap());
    }

    /** @test */
    public function driver_will_remove_a_language_from_the_map_if_it_has_no_models_in_it_when_deleting_a_model_from_all_languages()
    {
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
            'en' => [
                Article::class => [1, 2]
            ],
            'es' => [
                Article::class => [1]
            ]
        ], $this->getMap());

        $this->driver->removeModelFromAllLanguages($articleToBeRemoved);

        $this->assertEquals([
            'en' => [
                Article::class => [2]
            ]
        ], $this->getMap());
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
        $initialMap = $this->getMap();
        $this->assertEquals([
            'en' => $modelInMap
        ], $initialMap);

        $this->driver->storeTranslationsForModel($article, 'es', [
            'title' => 'Espagnolo titlo'
        ]);
        $this->driver->syncModelsForLanguage('es', $article);

        $newMap = $this->getMap();
        $this->assertEquals([
            'en' => $modelInMap,
            'es' => $modelInMap
        ], $newMap);
    }

    /** @test */
    public function driver_can_remove_a_model_instance_from_map_when_syncing()
    {
        $this->withoutJobs();
        $modelInMap = [
            Article::class => [1]
        ];
        $method = $this->getProtectedMethod('getLanguageModelMap');
        $article = $this->makeAndSyncArticle([
            'en' => [
                'title' => 'Title in English',
            ],
            'es' => [
                'title' => 'Espagnolo titlo'
            ]
        ]);
        $initialMap = $method->invoke($this->driver);
        $this->assertEquals([
            'en' => $modelInMap,
            'es' => $modelInMap
        ], $initialMap);

        $this->driver->deleteLanguagesForModel($article, ['es']);
        $this->driver->syncModelsForLanguage('es', $article);

        $newMap = $method->invoke($this->driver);
        $this->assertEquals([
            'en' => $modelInMap,
        ], $newMap);
    }
}
