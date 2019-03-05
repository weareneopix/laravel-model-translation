<?php

namespace MisaNeopix\LaravelModelTranslation\Drivers;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Assert as PHPUnit;
use MisaNeopix\LaravelModelTranslation\Contracts\TranslationDriver;

class ArrayTranslationDriver implements TranslationDriver
{
    /** @var array */
    private $translations = [];

    public function storeTranslationsForModel(Model $model, string $language, array $translations): bool
    {
        $this->translations[$model->getModelIdentifier()][$model->getInstanceIdentifier()][$language] = $translations;

        return true;
    }

    public function getTranslationsForModel(Model $model, string $language): array
    {
        $modelIdentifier = $model->getModelIdentifier();
        $instanceIdentifier = $model->getInstanceIdentifier();

        if (isset($this->translations[$modelIdentifier][$instanceIdentifier][$language])) {
            return $this->translations[$modelIdentifier][$instanceIdentifier][$language];
        }

        return [];
    }

    public function getTranslationsForModels(Collection $models, string $language): Collection
    {
        return $models->mapWithKeys(function (Model $model) use ($language) {
            return [
                $model->getInstanceIdentifier() => $this->getTranslationsForModel($model, $language),
            ];
        });
    }

    public function getAvailableLanguagesForModel(Model $model): array
    {
        if (isset($this->translations[$model->getModelIdentifier()][$model->getInstanceIdentifier()])) {
            return array_keys($this->translations[$model->getModelIdentifier()][$model->getInstanceIdentifier()]);
        }

        return [];
    }

    public function getModelsAvailableInLanguage(string $modelIdentifier, string $language): array
    {
        $instances = $this->translations[$modelIdentifier] ?? [];

        if (empty($instances)) {
            return $instances;
        }

        $modelsWithLanguage = array_filter($instances, function ($modelLanguages) use ($language) {
            return array_key_exists($language, $modelLanguages);
        });

        return array_keys($modelsWithLanguage);
    }

    public function putTranslationsForModel(Model $model, string $language, array $translations): bool
    {
        $modelIdentifier = $model->getModelIdentifier();
        $instanceIdentifier = $model->getInstanceIdentifier();

        $this->translations[$modelIdentifier][$instanceIdentifier][$language] = $translations;

        return true;
    }

    public function patchTranslationsForModel(Model $model, string $language, array $translations): bool
    {
        $modelIdentifier = $model->getModelIdentifier();
        $instanceIdentifier = $model->getInstanceIdentifier();

        foreach ($translations as $attribute => $translation) {
            $this->translations[$modelIdentifier][$instanceIdentifier][$language][$attribute] = $translation;
        }

        return true;
    }

    public function deleteAllTranslationsForModel(Model $model): bool
    {
        unset($this->translations[$model->getModelIdentifier()][$model->getInstanceIdentifier()]);

        return true;
    }

    public function deleteLanguagesForModel(Model $model, array $languages): bool
    {
        foreach ($languages as $language) {
            unset($this->translations[$model->getModelIdentifier()][$model->getInstanceIdentifier()][$language]);
        }

        return true;
    }

    public function deleteAttributesForModel(Model $model, array $attributes, string $language = null): bool
    {
        $languages = ($language !== null)
            ? [$language]
            : array_keys($this->translations[$model->getModelIdentifier()][$model->getInstanceIdentifier()]);

        foreach ($languages as $language) {
            foreach ($attributes as $attribute) {
                unset($this->translations[$model->getModelIdentifier()][$model->getInstanceIdentifier()][$language][$attribute]);
            }
        }

        return true;
    }

    /**
     * Assert that a model has an attribute translation stored in the provided language.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param string $attribute
     * @param string $language
     */
    public function assertModelHasTranslation(Model $model, string $attribute, string $language)
    {
        PHPUnit::assertTrue(
            $this->translationExists($model, $attribute, $language),
            "Provided model's {$attribute} was not translated to {$language}"
        );
    }

    /**
     * Assert that the model hasn't got a translation for the specified attribute in the specified language.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param string $attribute
     * @param string $language
     */
    public function assertModelNotHasTranslation(Model $model, string $attribute, string $language)
    {
        PHPUnit::assertFalse(
            $this->translationExists($model, $attribute, $language),
            "The provided model has {$attribute} translated to {$language}"
        );
    }

    /**
     * Assert that the model's attribute's translation in the provided language has the provided value.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param string $attribute
     * @param string $language
     * @param string $expected
     */
    public function assertModelTranslation(Model $model, string $attribute, string $language, string $expected)
    {
        if (! $this->translationExists($model, $attribute, $language)) {
            throw new \InvalidArgumentException('The requested translation does not exist.');
        }

        $translation = $this->translations[$model->getModelIdentifier()]
                                          [$model->getInstanceIdentifier()]
                                          [$language]
                                          [$attribute];

        PHPUnit::assertEquals(
            $expected,
            $translation,
            "The provided model's {$attribute} was translated as {$translation} instead of the expected {$expected}."
        );
    }

    protected function translationExists(Model $model, string $translation, string $language)
    {
        return isset(
            $this->translations[$model->getModelIdentifier()][$model->getInstanceIdentifier()][$language][$translation]
        );
    }
}
