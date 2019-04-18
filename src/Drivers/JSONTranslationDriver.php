<?php

namespace WeAreNeopix\LaravelModelTranslation\Drivers;

use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\FilesystemAdapter as StorageDisk;
use WeAreNeopix\LaravelModelTranslation\Contracts\TranslationDriver;
use WeAreNeopix\LaravelModelTranslation\Jobs\SyncModelLanguageMapping;
use WeAreNeopix\LaravelModelTranslation\Jobs\RemoveModelFromLanguageModelMap;

class JSONTranslationDriver implements TranslationDriver
{
    /** @var StorageDisk */
    protected $disk;

    public function __construct(StorageDisk $disk)
    {
        $this->disk = $disk;
    }

    public function storeTranslationsForModel(Model $model, string $language, array $translations): bool
    {
        $pathFromDiskRoot = $this->getJsonPathForModel($model, $language);

        $content = json_encode($translations);

        SyncModelLanguageMapping::dispatch($model, $language);

        return $this->disk->put($pathFromDiskRoot, $content);
    }

    public function getTranslationsForModel(Model $model, string $language): array
    {
        $path = $this->getJsonPathForModel($model, $language);

        if (! $this->disk->exists($path)) {
            return [];
        }

        $contents = $this->disk->get($this->getJsonPathForModel($model, $language));

        return json_decode($contents, true);
    }

    public function getTranslationsForModels(Collection $models, string $language): Collection
    {
        return $models->mapWithKeys(function (Model $model) use ($language) {
            $path = $this->getJsonPathForModel($model, $language);
            $translationArray = [];

            if ($this->disk->exists($path)) {
                $translationsJson = $this->disk->get($this->getJsonPathForModel($model, $language));
                $translationArray = json_decode($translationsJson, true);
            }

            return [
                $model->getInstanceIdentifier() => $translationArray,
            ];
        });
    }

    public function getAvailableLanguagesForModel(Model $model): array
    {
        $path = $this->getJsonPathForModel($model);

        return array_map(function ($jsonFile) {
            return basename($jsonFile, '.json');
        }, $this->disk->files($path));
    }

    public function getModelsAvailableInLanguage(string $modelIdentifier, string $language): array
    {
        $map = $this->getLanguageModelMap($language);

        return $map[$modelIdentifier] ?? [];
    }

    public function putTranslationsForModel(Model $model, string $language, array $translations): bool
    {
        return $this->storeTranslationsForModel($model, $language, $translations);
    }

    public function patchTranslationsForModel(Model $model, string $language, array $translations): bool
    {
        $existingTranslations = $this->getTranslationsForModel($model, $language);

        $newTranslations = array_merge($existingTranslations, $translations);

        return $this->storeTranslationsForModel($model, $language, $newTranslations);
    }

    public function deleteAllTranslationsForModel(Model $model): bool
    {
        $path = $this->getJsonPathForModel($model);

        RemoveModelFromLanguageModelMap::dispatch($model);

        return $this->disk->deleteDirectory($path);
    }

    public function deleteLanguagesForModel(Model $model, array $languages): bool
    {
        $modelDir = $this->getJsonPathForModel($model).DIRECTORY_SEPARATOR;
        foreach ($languages as $language) {
            $this->disk->delete($modelDir."{$language}.json");

            SyncModelLanguageMapping::dispatch($model, $language);
        }

        return true;
    }

    public function deleteAttributesForModel(Model $model, array $attributes, string $language = null): bool
    {
        $modelDir = $this->getJsonPathForModel($model);
        if ($language !== null) {
            $paths = [$modelDir.DIRECTORY_SEPARATOR."{$language}.json"];
            if (! $this->disk->exists($paths[0])) {
                return true;
            }
        } else {
            $paths = $this->disk->files($modelDir);
        }

        foreach ($paths as $filePath) {
            $translations = json_decode($this->disk->get($filePath), true);
            $newTranslations = array_diff_key($translations, array_flip($attributes));

            if (empty($newTranslations)) {
                $this->disk->delete($filePath);
            } else {
                $this->disk->put($filePath, json_encode($newTranslations));
            }

            $language = pathinfo($filePath)['filename'];
            SyncModelLanguageMapping::dispatch($model, $language);
        }

        return true;
    }


    public function syncModelsForLanguage(string $language, Model $model)
    {
        if (in_array($language, $this->getAvailableLanguagesForModel($model))) {
            $this->addModelToLanguageMap($model, $language);
        } else {
            $this->removeModelFromLanguageMap($model, $language);
        }
    }

    protected function addModelToLanguageMap(Model $model, string $language)
    {
        $modelIdentifier = $model->getModelIdentifier();
        $instanceIdentifier = $model->getInstanceIdentifier();
        $map = $this->getLanguageModelMap($language);

        if (!array_key_exists($modelIdentifier, $map)) {
            $map[$modelIdentifier] = [$instanceIdentifier];
            $this->saveMap($map, $language);
        } elseif (!in_array($instanceIdentifier, $map[$modelIdentifier])) {
            $map[$modelIdentifier][] = $instanceIdentifier;
            $this->saveMap($map, $language);
        }
    }

    protected function removeModelFromLanguageMap(Model $model, string $language)
    {
        $modelIdentifier = $model->getModelIdentifier();
        $modelInstanceIdentifier = $model->getInstanceIdentifier();
        $map = $this->getLanguageModelMap($language);

        $instances = collect($map[$modelIdentifier] ?? [])->filter(function ($instanceIdentifier) use ($modelInstanceIdentifier) {
            return $instanceIdentifier != $modelInstanceIdentifier;
        })->values();

        $map[$modelIdentifier] = $instances->toArray();

        $map = $this->removeRedundancyFromMap($map, $language);

        (empty($map)) ? $this->removeMap($language) : $this->saveMap($map, $language);
    }

    public function removeModelFromAllLanguages(Model $model)
    {
        $languages = $this->getAvailableLanguagesForModel($model);
        foreach ($languages as $language) {
            $this->removeModelFromLanguageMap($model, $language);
        }
    }


    protected function initializeMap(string $language)
    {
        $this->disk->put($this->mapName($language), json_encode([]));
    }

    protected function mapName(string $language)
    {
        return "meta" . DIRECTORY_SEPARATOR . "{$language}.json";
    }

    protected function getLanguageModelMap($language)
    {
        if (!$this->disk->has($this->mapName($language))) {
            $this->initializeMap($language);
            return [];
        }

        return json_decode($this->disk->get($this->mapName($language)), true);
    }

    protected function removeRedundancyFromMap(array $map) {
        foreach ($map as $modelIdentifier => $instances) {
            if (empty($instances)) {
                unset($map[$modelIdentifier]);
            }
        }
        return $map;
    }

    protected function saveMap(array $map, string $language)
    {
        $this->disk->put($this->mapName($language), json_encode($map));
    }

    protected function removeMap(string $language)
    {
        $this->disk->delete($this->mapName($language));
    }


    protected function getJsonPathForModel(Model $model, string $language = null)
    {
        $instanceIdentifier = $model->getInstanceIdentifier();
        $modelIdentifier = $this->normalizeModelIdentifier($model->getModelIdentifier());

        $path = $modelIdentifier.DIRECTORY_SEPARATOR.$instanceIdentifier;
        if ($language !== null) {
            $path .= DIRECTORY_SEPARATOR."{$language}.json";
        }

        return $path;
    }

    protected function normalizeModelIdentifier($modelIdentifier)
    {
        $modelIdentifier = str_replace('\\', '_', $modelIdentifier);

        return Str::slug($modelIdentifier);
    }
}
