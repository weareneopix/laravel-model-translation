<?php

namespace MisaNeopix\LaravelModelTranslation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Filesystem\FilesystemAdapter as StorageDisk;
use MisaNeopix\LaravelModelTranslation\Drivers\JSONTranslationDriver;
use MisaNeopix\LaravelModelTranslation\Commands\TestTranslationExtensions;

class ModelTranslationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        if ($this->app->runningInConsole()) {
            $this->commands(TestTranslationExtensions::class);
        }

        $this->app->singleton('translation', function ($app) {
            return new TranslationManager($app);
        });

        $this->app->when(JSONTranslationDriver::class)
                  ->needs(StorageDisk::class)
                  ->give(function () {
                      return Storage::disk('translation');
                  });

        $this->publishConfig();
        $this->publishMigrations();

        $this->createTranslationsDisk();

        $this->registerCollectionMacro();
    }

    /**
     * Register the config file publishing.
     *
     * @return void
     */
    private function publishConfig()
    {
        $this->publishes([
            __DIR__.'/../config/translation.php' => config_path('translation.php'),
        ], 'config');
    }

    /**
     * Register the migration file publishing.
     *
     * @return void
     */
    private function publishMigrations()
    {
        $publishedMigrationName = date('Y_m_d_His').'_create_translations_table.php';
        $publishPath = database_path('migrations'.DIRECTORY_SEPARATOR.$publishedMigrationName);
        $this->publishes([
            __DIR__.'/../migrations/2019_02_25_163419_create_translations_table.php' => $publishPath,
        ], 'migrations');
    }

    /**
     * Create the custom Filesystem disk for storing JSON-based translations.
     *
     * @return void
     */
    private function createTranslationsDisk()
    {
        $basePath = config('translation.json.base_path', 'app/translations');

        config()->set('filesystems.disks.translation', [
            'driver' => 'local',
            'root' => storage_path($basePath),
        ]);
    }

    /**
     * Register the Eloquent Collection macro used for setting translations on an array of models.
     *
     * @return void
     */
    private function registerCollectionMacro()
    {
        Collection::macro('setLanguage', function (string $language, bool $loadLanguage = true) {
            if ($this->count() < 1) {
                return;
            }

            $this->each(function (Model $model) use ($language) {
                // Set the language on the model but do not load it.
                // We will load the language from the collection method if the user has requested it.
                $model->setLanguage($language, false);
            });

            // If requested, we will load the translations for the specified language for all the present models.
            // We will do this all in one request to avoid N calls.
            if ($loadLanguage) {
                $translations = Translation::getTranslationsForModels($this, $language);
                $this->each(function (Model $model) use ($translations, $language) {
                    $model->setTranslations(
                        $translations->get($model->getInstanceIdentifier(), [])
                    );
                });
            }

            return $this;
        });
    }
}
