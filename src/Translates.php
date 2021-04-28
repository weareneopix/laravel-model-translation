<?php

namespace WeAreNeopix\LaravelModelTranslation;

use Illuminate\Support\Facades\App;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

trait Translates
{
	/**
	 * The language the model is currently loaded in.
	 *
	 * @var string
	 */
	protected $selectedLanguage;

	/**
	 * Array of the model's attributes that should be translated.
	 *
	 * @var array
	 */
	// protected $translatable = [];

	/**
	 * Array that holds the translated values while saving is performed.
	 *
	 * @var array
	 */
	protected $translatedAttributes = [];

	/**
	 * Returns the Model identifier used for storing translations in combination with the instance identifier.
	 *
	 * @return string
	 */
	public function getModelIdentifier(): string
	{
		return static::class;
	}

	/**
	 * Returns the instance identifier used for storing translations in combination with the model identifier.
	 *
	 * @return string
	 */
	public function getInstanceIdentifier(): string
	{
		return (string) $this->getKey();
	}

	/**
	 * Returns the attribute name of the instance identifier.
	 *
	 * @return string
	 */
	protected function getInstanceIdentifierName(): string
	{
		return (string) $this->getKeyName();
	}

	/**
	 * Set the $translatable array effectively changing which attributes are translatable.
	 * It is highly recommended you avoid using this method, unless absolutely necessary.
	 *
	 * @param array $translatable
	 * @return self
	 */
	public function setTranslatable(array $translatable): self
	{
		$this->translatable = $translatable;

		return $this;
	}

	/**
	 * Return all the translatable attribute names or only the ones present in the first argument.
	 *
	 * @param array $only
	 * @return array
	 */
	public function getTranslatable(array $only = []): array
	{
		return (empty($only))
			? $this->translatable
			: array_intersect($this->translatable, $only);
	}

	/**
	 * Set the current language on the instance.
	 * Automatically loads the language if it hasn't been loaded previously.
	 *
	 * @param  string $language
	 * @return self
	 */
	public function setLanguage(string $language): self
	{
		$this->selectedLanguage = $language;

		// We load the language automatically to avoid interferences between languages
		$this->loadLanguage($language);

		return $this;
	}

	/**
	 * Checks if a language has been previously loaded and is present in the $loadedLanguages array.
	 *
	 * @param  string $language
	 * @return bool
	 */
	public function languageLoaded(string $language): bool
	{
		return $this->selectedLanguage == $language;
	}

	/**
	 * Load a language and store it in the $loadedLanguages array.
	 *
	 * @param  string $language
	 * @return self
	 */
	public function loadLanguage(string $language): array
	{
		$translations = Translation::getTranslationsForModel($this, $language);
		$this->setTranslations($translations);

		return $translations;
	}

	/**
	 * Set the provided data as a loaded language.
	 *
	 * @param  array $translations
	 * @return self
	 */
	public function setTranslations(array $translations): self
	{
		/*
		 * We perform this translation in order to ensure
		 * that even the translations that aren't present
		 * get overridden within the attributes array
		 */
		$translations = collect($this->translatable)->mapWithKeys(
		function ($attribute) use ($translations) {
			$translation = $translations[$attribute] ?? null;

			return [
				$attribute => $translation,
			];
		}
	)->toArray();

		$this->attributes = array_merge($this->attributes, $translations);

		return $this;
	}

	/**
	 * Reload only the languages that have already been loaded.
	 *
	 * @return self
	 */
	public function reloadTranslations(): self
	{
		$language = $this->getActiveLanguage();

		$this->loadLanguage($language);

		return $this;
	}

	/**
	 * Return the currently used language. Defaults to the app locale if $selectedLanguage is null.
	 *
	 * @return string
	 */
	public function getActiveLanguage(): string
	{
		return $this->selectedLanguage ?? App::getLocale();
	}

	/**
	 * Override Laravel's refresh() method to refresh the translations as well.
	 *
	 * @return self
	 */
	public function refresh(): self
	{
		parent::refresh();

		$this->reloadTranslations();

		return $this;
	}

	/**
	 * Override Laravel's getAttribute() method to check if the requested attribute is translatable.
	 * If the attribute is translatable and the language not loaded, it will load the language.
	 *
	 * @return mixed
	 */
	public function getAttribute($attribute)
	{
		$activeLanguage = $this->getActiveLanguage();

		if ($this->attributeIsTranslatable($attribute) && ! $this->languageLoaded($activeLanguage)) {
			$this->loadLanguage($activeLanguage);
		}

		return parent::getAttribute($attribute);
	}

	/**
	 * Checks whether an attribute is translatable.
	 *
	 * @return bool
	 */
	public function attributeIsTranslatable($attribute)
	{
		return in_array($attribute, $this->translatable);
	}

	/**
	 * Persist the translations using the loaded TranslationDriver.
	 *
	 * @return void
	 */
	protected function persistTranslations()
	{
		Translation::patchTranslationsForModel($this, $this->getActiveLanguage(), $this->translatedAttributes);
	}

	/**
	 * Merge the translated attributes with the translated ones.
	 *
	 * @return void
	 */
	protected function mergeTranslationsWithAttributes()
	{
		$this->attributes = array_merge($this->attributes, $this->translatedAttributes);
	}

	/**
	 * Separate the translatable attribute values from the $attributes array.
	 *
	 * @return array
	 */
	public function separateTranslationsFromAttributes(): array
	{
		$attributes = collect($this->attributes);

		// Extract the translated values
		$translatables = $attributes->only($this->translatable);
		$this->translatedAttributes = $translatables->toArray();

		// Keep only the non-translatable attributes
		$this->attributes = $attributes->forget($this->translatable)->toArray();

		return $this->translatedAttributes;
	}

	/**
	 * Remove all translations from the persistent storage.
	 * This is called after the model is deleted.
	 *
	 * @param  string $language
	 * @return bool
	 */
	public function deleteTranslations(...$languages): bool
	{
		if (empty($languages)) {
			return Translation::deleteAllTranslationsForModel($this);
		}

		return Translation::deleteLanguagesForModel($this, $languages);
	}

	/**
	 * Check if the class uses the SoftDeletes trait.
	 *
	 * @return bool
	 */
	protected function usesSoftDelete(): bool
	{
		return in_array(SoftDeletes::class, class_uses_recursive(static::class));
	}

	/**
	 * Register model event listeners which ensure that the translatable attributes are synced
	 * when the model is being saved, deleted or restored.
	 */
	public static function bootTranslates()
	{
		static::saving(
			function (Model $model) {
				$model->separateTranslationsFromAttributes();
			}
		);

		// We have to persist the translations after the model has been saved
		// to avoid the case when saving a new model which doesn't exist prior to saving.
		static::saved(
			function (Model $model) {
				$model->persistTranslations();

				$model->mergeTranslationsWithAttributes();
			}
		);

		static::deleted(
			function (Model $model) {
				if ($model->usesSoftDelete() && ! $model->forceDeleting) {
					return true;
				}
				$model->deleteTranslations();
			}
		);
	}

	/**
	 * We make an accessor for this in order to make it possible to add it to the $appends model property.
	 *
	 * @return array
	 */
	public function getAvailableLanguagesAttribute()
	{
		return Translation::getAvailableLanguagesForModel($this);
	}

	/**
	 * We make an accessor for this in order to make it possible to add it to the $appends model property.
	 *
	 * @return string
	 */
	public function getSelectedLanguageAttribute()
	{
		return $this->getActiveLanguage();
	}

	/**
	 * Constrain a query to Models Models available in the provided language.
	 *
	 * @param \Illuminate\Database\Eloquent\Builder $query
	 * @param string $language
	 * @return \Illuminate\Database\Eloquent\Builder
	 */
	public function scopeInLanguage(Builder $query, string $language)
	{
		$availableModelIds = Translation::getModelsAvailableInLanguage($this->getModelIdentifier(), $language);
		$constraint = $this->getTable().'.'.$this->getInstanceIdentifierName();

		return $query->whereIn($constraint, $availableModelIds);
	}
}
