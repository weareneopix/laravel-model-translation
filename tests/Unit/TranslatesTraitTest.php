<?php

namespace MisaNeopix\LaravelModelTranslation\Test\Unit;

use Illuminate\Support\Facades\App;
use MisaNeopix\LaravelModelTranslation\Translation;
use MisaNeopix\LaravelModelTranslation\Test\TestCase;
use MisaNeopix\LaravelModelTranslation\Test\Dependencies\Article;
use MisaNeopix\LaravelModelTranslation\Test\Dependencies\ArticleWithSoftDeletes as SoftDeletingArticle;

class TranslatesTraitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Translation::fake();
    }

    protected function createArticlesTable()
    {
        $this->loadMigrationsFrom(__DIR__.'/../Dependencies');
    }

    protected function createArticles(int $count = 5, array $articleData = [])
    {
        $articleData = array_merge([
            'title' => null,
            'body' => null,
            'author' => null,
            'published_at' => null,
        ], $articleData);

        $toBeInserted = [];
        for ($i = 0; $i < $count; $i++) {
            $toBeInserted[] = $articleData;
        }
        Article::query()->insert($toBeInserted);
    }

    /** @test */
    public function trait_will_save_attribute_as_translation_when_it_is_translatable()
    {
        $article = new Article();
        $article->setLanguage('en');
        $article->title = 'Article Title';

        $translations = $article->separateTranslationsFromAttributes();

        $this->arrayHasKey('title', $translations);
        $this->assertArrayNotHasKey('title', $article->getAttributes());
    }

    /** @test */
    public function trait_will_save_attribute_as_basic_attribute_when_it_is_not_translatable()
    {
        $article = new Article();
        $article->setLanguage('en');
        $article->author = 'John Doe';

        $translations = $article->separateTranslationsFromAttributes();

        $this->assertArrayNotHasKey('author', $translations);
        $this->assertArrayHasKey('author', $article->getAttributes());
    }

    /** @test */
    public function user_can_explicitly_set_a_translation_for_a_translatable_value()
    {
        $article = new Article();
        $article->setLanguage('en');
        $article->setTranslations([
            'title' => 'New Article',
        ]);

        $translations = $article->separateTranslationsFromAttributes();

        $this->assertArrayHasKey('title', $translations);
        $this->assertArrayNotHasKey('title', $article->getAttributes());
    }

    /** @test */
    public function translations_are_subject_to_mutators()
    {
        $this->createArticlesTable();
        $article = Article::create();
        $article->setTranslatable(['test_translation']);
        $article->setLanguage('en');

        $article->test_translation = 'Transform me';
        $article->save();

        $translations = Translation::getTranslationsForModel($article, 'en');
        $this->assertEquals([
            'test_translation' => strrev('Transform me'),
        ], $translations);
    }

    /** @test */
    public function translations_are_subject_to_accessors()
    {
        $this->createArticlesTable();
        $article = Article::create();
        $article->setTranslatable(['test_translation']);
        Translation::storeTranslationsForModel($article, 'en', [
            'test_translation' => 'i wanna be uppercase',
        ]);

        $article->setLanguage('en');

        $this->assertEquals('I WANNA BE UPPERCASE', $article->test_translation);
    }

    /** @test */
    public function only_translatable_attributes_will_be_saved_when_explicitly_setting_translations()
    {
        $article = new Article();
        $article->setLanguage('en');

        $article->setTranslations([
            'title' => 'New Article',
            'not_translatable' => 'Some value',
        ]);

        $translations = $article->separateTranslationsFromAttributes();
        $this->assertArrayNotHasKey('not_translatable', $translations);
        $this->assertArrayNotHasKey('not_translatable', $article->getAttributes());
    }

    /** @test */
    public function filling_a_model_will_fill_the_translations_too()
    {
        $article = new Article();
        $article->setLanguage('en');

        $article->fill([
            'author' => 'John Doe',
            'title' => 'New Title',
        ]);
        $translations = $article->separateTranslationsFromAttributes();

        $this->assertArrayHasKey('title', $translations);
        $this->assertArrayHasKey('author', $article->getAttributes());
    }

    /** @test */
    public function translations_will_not_be_saved_if_they_are_not_fillable()
    {
        $this->createArticlesTable();
        $article = Article::create();
        $article->fillable(['title'])
                ->setTranslatable([
                    'title', 'body',
                ]);
        $article->setLanguage('en');

        $article->fill([
            'title' => 'Some Title',
            'Body' => 'Some Body',
        ]);
        $article->save();

        $translations = Translation::getTranslationsForModel($article, 'en');

        $this->assertEquals([
            'title' => 'Some Title',
            'body' => null,
        ], $translations);
    }

    /** @test */
    public function translations_will_be_automatically_persisted_when_the_model_is_being_persisted()
    {
        $this->createArticlesTable();
        $article = new Article();
        $article->setLanguage('en');
        $article->title = 'New Article';

        $article->save();

        Translation::assertModelHasTranslation($article, 'title', 'en');
        Translation::assertModelTranslation($article, 'title', 'en', 'New Article');
    }

    /** @test */
    public function trait_will_automatically_detect_app_locale_if_a_language_has_not_been_explicitly_set()
    {
        $article = new Article();
        Translation::storeTranslationsForModel($article, 'rs', [
            'title' => 'Novi clanak',
        ]);
        $this->assertNull($article->title);

        App::setLocale('rs');

        $this->assertEquals($article->title, 'Novi clanak');
    }

    /** @test */
    public function user_can_set_the_selected_language_on_a_model()
    {
        $this->createArticlesTable();
        $article = Article::create();
        Translation::storeTranslationsForModel($article, 'ru', [
            'title' => 'Article in Russian',
        ]);
        App::setLocale('en');
        // We assert that the model hasn't got title loaded
        $this->assertArrayNotHasKey('title', $article->getAttributes());
        $this->assertEquals('en', $article->getActiveLanguage());

        $article->setLanguage('ru');

        $this->assertEquals('Article in Russian', $article->getAttributes()['title']);
        $this->assertEquals('ru', $article->getActiveLanguage());
    }

    /** @test */
    public function setting_a_language_on_a_model_will_override_all_the_loaded_translations()
    {
        $this->createArticlesTable();
        $article = Article::create();
        Translation::storeTranslationsForModel($article, 'en', [
            'title' => 'Title in English',
            'body' => 'Body in English',
            'description' => 'Description in English',
        ]);
        Translation::storeTranslationsForModel($article, 'ru', [
            'title' => 'Title in Russian',
            'body' => 'Body in Russian',
        ]);
        $article->setLanguage('en');
        $this->assertEquals('Title in English', $article->title);
        $this->assertEquals('Description in English', $article->description);

        $article->setLanguage('ru');

        $this->assertEquals('Title in Russian', $article->title);
        $this->assertNull($article->description);
    }

    /** @test */
    public function trait_will_use_selected_language_if_it_has_been_set()
    {
        $this->createArticlesTable();
        $article = Article::create();
        Translation::storeTranslationsForModel($article, 'en', [
            'title' => 'New Article',
        ]);
        Translation::storeTranslationsForModel($article, 'rs', [
            'title' => 'Novi clanak',
        ]);

        App::setLocale('en');
        $article->setLanguage('rs');

        $this->assertEquals('Novi clanak', $article->title);
    }

    /** @test */
    public function user_can_set_the_selected_language_on_a_collection_of_models()
    {
        $this->createArticlesTable();
        $this->createArticles();
        $articles = Article::all();
        $articles->each(function (Article $article, $key) {
            Translation::storeTranslationsForModel($article, 'en', [
                'title' => 'New Article '.$key,
            ]);
        });

        $articles->setLanguage('en');

        $articles->each(function (Article $article, $key) {
            $this->assertEquals('en', $article->getActiveLanguage());
            $this->assertEquals('New Article '.$key, $article->title);
        });
    }

    /** @test */
    public function user_can_set_the_selected_language_on_a_collection_of_models_without_loading_the_translations()
    {
        $this->createArticlesTable();
        $models = collect([
            Article::create(),
            Article::create(),
        ]);
        $models->each(function (Article $article) {
            Translation::storeTranslationsForModel($article, 'en', [
                'title' => "Article number {$article->id}",
            ]);
        });

        $models->setLanguage('en', false);

        $models->each(function (Article $article) {
            $this->assertNull($article->getOriginal('title'));
        });
    }

    /** @test */
    public function translations_will__automatically_be_deleted_when_the_model_is_being_deleted()
    {
        $this->createArticlesTable();
        $article = Article::create();

        $article->setLanguage('en');
        $article->title = 'New Article';
        $article->save();

        Translation::assertModelHasTranslation($article, 'title', 'en');

        $article->delete();

        Translation::assertModelNotHasTranslation($article, 'title', 'en');
    }

    /** @test */
    public function user_can_explicitly_delete_translations_without_deleting_the_model()
    {
        $this->createArticlesTable();
        $article = Article::create();
        $article->setLanguage('en');
        $article->title = 'New Article';
        $article->save();
        Translation::assertModelHasTranslation($article, 'title', 'en');

        $article->deleteTranslations();

        Translation::assertModelNotHasTranslation($article, 'title', 'en');
    }

    /** @test */
    public function user_can_explicitly_delete_translations_for_a_specific_language_without_deleting_the_model()
    {
        $this->createArticlesTable();
        $article = Article::create();
        Translation::storeTranslationsForModel($article, 'en', [
            'title' => 'New Article',
        ]);
        Translation::storeTranslationsForModel($article, 'rs', [
            'title' => 'Novi clanak',
        ]);

        $article->deleteTranslations('rs');

        Translation::assertModelHasTranslation($article, 'title', 'en');
        Translation::assertModelNotHasTranslation($article, 'title', 'rs');
    }

    /** @test */
    public function user_can_explicitly_delete_translations_for_multiple_languages()
    {
        $this->createArticlesTable();
        $article = Article::create();
        Translation::storeTranslationsForModel($article, 'en', [
            'title' => 'New Article',
        ]);
        Translation::storeTranslationsForModel($article, 'rs', [
            'title' => 'Novi clanak',
        ]);
        Translation::storeTranslationsForModel($article, 'es', [
            'title' => 'Nuovo Articulo',
        ]);

        $article->deleteTranslations('es', 'rs');

        Translation::assertModelHasTranslation($article, 'title', 'en');
        Translation::assertModelNotHasTranslation($article, 'title', 'es');
        Translation::assertModelNotHasTranslation($article, 'title', 'rs');
    }

    /** @test */
    public function languages_will_automatically_be_reloaded_when_the_model_is_being_refreshed()
    {
        $this->createArticlesTable();
        $article = Article::create();
        Translation::storeTranslationsForModel($article, 'en', [
            'title' => 'Old Article Title',
        ]);
        $article->setLanguage('en');
        $this->assertEquals('Old Article Title', $article->title);

        Translation::patchTranslationsForModel($article, 'en', [
            'title' => 'New Article Title',
        ]);
        $article->refresh();

        $this->assertEquals('New Article Title', $article->title);
    }

    /** @test */
    public function model_translations_will_not_be_deleted_upon_soft_deleting_a_model()
    {
        $this->createArticlesTable();
        $article = SoftDeletingArticle::create();
        $article->setLanguage('en');
        $article->title = 'New Title';
        $article->save();

        $article->delete();
        Translation::assertModelHasTranslation($article, 'title', 'en');

        $article->forceDelete();
        Translation::assertModelNotHasTranslation($article, 'title', 'en');
    }

    /** @test */
    public function user_can_get_the_selected_language()
    {
        $article = Article::make();
        $article->setLanguage('ru');

        $retrievedLanguage = $article->selected_language;

        $this->assertEquals('ru', $retrievedLanguage);
    }

    /** @test */
    public function user_will_get_the_app_locale_if_no_language_has_been_set()
    {
        $article = Article::make();
        App::setLocale('cr');

        $selectedLanguage = $article->selected_language;

        $this->assertEquals('cr', $selectedLanguage);
    }

    /** @test */
    public function user_can_append_the_selected_language_to_the_model_for_serialization()
    {
        $article = Article::make();
        $article->setLanguage('nl');
        $article->setAppends(['selected_language']);
        $article->fill([
            'title' => 'Some Article',
            'body' => 'Some body',
        ]);

        $serialized = $article->toArray();

        $this->assertEquals([
            'body' => 'Some body',
            'selected_language' => 'nl',
            'title' => 'Some Article',
            'description' => null,
        ], $serialized);
    }

    /** @test */
    public function user_can_get_an_array_of_languages_available_for_the_model()
    {
        $this->createArticlesTable();
        $article = Article::create();
        Translation::storeTranslationsForModel($article, 'en', [
            'title' => 'Some title',
        ]);
        Translation::storeTranslationsForModel($article, 'ru', [
            'title' => 'Some title in Romanian',
        ]);

        $availableLangauges = $article->available_languages;

        $this->assertEquals([
            'en', 'ru',
        ], $availableLangauges);
    }

    /** @test */
    public function user_will_get_an_empty_array_if_there_are_no_languages_available_for_the_model()
    {
        $this->createArticlesTable();
        $article = Article::create();

        $availableLanguages = $article->available_languages;

        $this->assertEquals([], $availableLanguages);
    }

    /** @test */
    public function user_can_append_the_available_languages_to_the_model_for_serialization()
    {
        $this->createArticlesTable();
        $article = Article::create([]);
        Translation::storeTranslationsForModel($article, 'en', [
            'title' => 'Title in English',
        ]);
        Translation::storeTranslationsForModel($article, 'ru', [
            'title' => 'Title in Russian',
        ]);
        $article->setLanguage('en');
        $article->setAppends(['available_languages']);
        $article->setHidden(['id', 'updated_at', 'created_at']);

        $serialized = $article->toArray();

        $this->assertEquals([
            'available_languages' => [
                'en',
                'ru',
            ],
            'title' => 'Title in English',
            'body' => null,
            'description' => null,
        ], $serialized);
    }

    /** @test */
    public function user_can_get_only_models_available_in_a_particular_language()
    {
        $this->createArticlesTable();
        $relevantArticles = collect([
            Article::create(),
            Article::create(),
        ]);
        $relevantArticles->each(function (Article $article) {
            $article->setLanguage('en');
            $article->update([
                'title' => 'Title in English',
            ]);
        });
        $irrelevantArticle = Article::create()->setLanguage('rs');
        $irrelevantArticle->update([
            'title' => 'Naslov na srpskom',
        ]);

        $articlesAvailableInEnglish = Article::inLanguage('en')->get();

        $this->assertCount(2, $articlesAvailableInEnglish);
        $this->assertEquals(
            $relevantArticles->pluck('id')->toArray(),
            $articlesAvailableInEnglish->pluck('id')->toArray()
        );
    }
}
