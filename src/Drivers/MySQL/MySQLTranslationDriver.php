<?php

namespace WeAreNeopix\LaravelModelTranslation\Drivers\MySQL;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use WeAreNeopix\LaravelModelTranslation\Contracts\TranslationDriver;

class MySQLTranslationDriver implements TranslationDriver
{
    private function queryForModel(Model $model, string $language = null): Builder
    {
        $query = Translation::where('translatable_type', $model->getModelIdentifier())
                          ->where('translatable_id', $model->getInstanceIdentifier());

        if ($language !== null) {
            $query->where('language', $language);
        }

        return $query;
    }

    public function storeTranslationsForModel(Model $model, string $language, array $translations): bool
    {
        $translations = collect($translations)->map(function ($translation, $attribute) use ($language, $model) {
            return [
                'translatable_type' => $model->getModelIdentifier(),
                'translatable_id' => $model->getInstanceIdentifier(),
                'language' => $language,
                'name' => $attribute,
                'value' => $translation,
            ];
        });

        return Translation::query()->insert($translations->toArray());
    }

    public function getTranslationsForModel(Model $model, string $language): array
    {
        return $this->queryForModel($model, $language)
                    ->get()
                    ->mapWithKeys(function (Translation $translation) {
                        return [
                            $translation->name => $translation->value,
                        ];
                    })
                    ->toArray();
    }

    public function getTranslationsForModels(Collection $models, string $language): Collection
    {
        $modelIdentifier = $models->first()->getModelIdentifier();
        $instanceIdentifiers = $models->map(function (Model $model) {
            return $model->getInstanceIdentifier();
        });

        $translations = Translation::where('translatable_type', $modelIdentifier)
                          ->whereIn('translatable_id', $instanceIdentifiers)
                          ->where('language', $language)
                          ->get()
                          ->groupBy('translatable_id');

        /*
         * First we map the translations to their Models, setting an empty collection as default
         * We need to do this because the method needs to return an empty array for each model that has no translations.
         */
        $translationsPerModel = $models->mapWithKeys(function (Model $model) use ($translations) {
            return [
                $model->getInstanceIdentifier() => $translations->get($model->getInstanceIdentifier(), collect([])),
            ];
        });

        // Then we transform the translations to match the expected output format
        return $translationsPerModel->map(function (Collection $instanceTranslations) {
            return $instanceTranslations->mapWithKeys(function (Translation $translation) {
                return [
                    $translation->name => $translation->value,
                ];
            })->toArray();
        });
    }

    public function getAvailableLanguagesForModel(Model $model): array
    {
        return $this->queryForModel($model)->groupBy('language')->pluck('language')->toArray();
    }

    public function getModelsAvailableInLanguage(string $modelIdentifier, string $language): array
    {
        return Translation::where('translatable_type', $modelIdentifier)
                          ->where('language', $language)
                          ->groupBy('translatable_id')
                          ->pluck('translatable_id')
                          ->toArray();
    }

    public function putTranslationsForModel(Model $model, string $language, array $translations): bool
    {
        $this->queryForModel($model, $language)
             ->delete();

        return $this->storeTranslationsForModel($model, $language, $translations);
    }

    private function mysqlCaseUpdate(array $translations)
    {
        $sql = 'CASE ';
        foreach ($translations as $attribute => $translation) {
            $sql .= "WHEN name = '{$attribute}' THEN '{$translation}' ";
        }
        $sql .= 'END';

        return $sql;
    }

    public function patchTranslationsForModel(Model $model, string $language, array $translations): bool
    {
        list($existing, $new) = $this->separateExistingFromNewTranslations($model, $language, $translations);

        if (!$this->storeTranslationsForModel($model, $language, $new)) {
            return false;
        }

        $sql = $this->mysqlCaseUpdate($existing);
        $translationNames = array_keys($existing);

        return $this->queryForModel($model, $language)
                    ->whereIn('name', $translationNames)
                    ->update([
                        'value' => DB::raw($sql),
                    ]);
    }

    private function separateExistingFromNewTranslations(Model $model, string $language, array $translations)
    {
        $existingTranslations = array_keys($this->getTranslationsForModel($model, $language));
        $translations = collect($translations);

        return [
            $translations->only($existingTranslations)->toArray(),
            $translations->except($existingTranslations)->toArray(),
        ];
    }

    public function deleteAllTranslationsForModel(Model $model): bool
    {
        return $this->deleteTranslationsForModel($model);
    }

    public function deleteLanguagesForModel(Model $model, array $languages): bool
    {
        return $this->queryForModel($model)
                      ->whereIn('language', $languages)
                      ->delete();
    }

    public function deleteAttributesForModel(Model $model, array $attributes, string $language = null): bool
    {
        return $this->deleteTranslationsForModel($model, $language, $attributes);
    }

    public function deleteTranslationsForModel(Model $model, string $language = null, array $attributes = null): bool
    {
        $query = $this->queryForModel($model, $language);

        if ($attributes !== null) {
            $query->whereIn('name', $attributes);
        }

        return $query->forceDelete();
    }
}
