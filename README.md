# A robust yet elegant system for translating Eloquent Models

[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Build Status](https://travis-ci.org/misa-neopix/laravel-model-translation.svg?branch=master)](https://travis-ci.org/misa-neopix/laravel-model-translation)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/misa-neopix/laravel-model-translation/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/misa-neopix/laravel-model-translation/?branch=master)
[![StyleCI](https://styleci.io/repos/173951106/shield?branch=master)](https://styleci.io/repos/173951106)


This package is meant to simplify the process of making Eloquent models translatable. It's aim is to deviate as little as possible from Laravel's
Eloquent API but still provide a satisfiable level of flexibility. This is achieved by utilizing a driver-based approach to storing translations
and a trait which allows Model instances to seamlessly interact with the translation storage. 

After setting the package up, all that takes to use your models with translations is this:

```php
$post = BlogPost::find(1); // An instance of the BlogPost model

App::setLocale('rs');
$post->title = 'Naslov na srpskom';
$post->save();

$post->setLanguage('en');
$post->title = 'Title in English';
$post->save();

$post->title; // Returns 'Title in English';
$post->setLanguage('rs')->title; // Returns 'Naslov na srpskom'
```

Since this is a driver-based solution, you have the full power to implement the architecture for persistently storing translations yourself. 
Of course, the package comes with two drivers out of the box, JSON and MySQL, but you are free to implement your own drivers and rely on whatever
architecture you prefer.

## Installation

You can install the package via composer:

``` bash
composer require misa-neopix/laravel-model-translation
```

This will download and install the package. The package supports Laravel's automatic package discovery, so the package's service provider will be 
automatically registered.

After installing via Composer, you should publish the configuration file using this command:

```bash
php artisan vendor:publish --provider="MisaNeopix\LaravelModelTranslation\ModelTranslationServiceProvider" --tag=config
```

This will copy the `translation.php` configuration file to your `config` folder.

## Configuration

After publishing the configuration file, your `config` directory will contain a `translation.php` file with the following contents:

```php
return [

        /*
         * The default driver to be used.
         * Out of the box, Laravel Model Translation comes with the 'mysql' and 'json' drivers
         */
        'driver' => 'json',

        /*
         * The configuration for the 'json' Laravel Model Translation driver.
         */
        'json' => [

            /*
             * The path where the translation JSONs should be stored
             */
            'base_path' => storage_path('app/translations')

        ],

        /*
         * The configuration for the 'mysql' Laravel Model Translation driver.
         * Only change this if you alter the migration published with the package.
         */
        'mysql' => [

            /*
             * The name of the table in which the translations are stored.
             */
            'table' => 'translations'
        ]
    ];
```

The package is preconfigured to use the `json` driver, and no additional setup is needed for it to work. This driver works immediately after installation.
However, you are free to change the default driver to `mysql` or any of your extensions. Detailed explanation on extensions can be found later in this document.

**Note:** further MySQL setup instructions are provided in the drivers section

## Usage

All you have to do in order to make a model translatable is to have it use the `MisaNeopix\LaravelModelTranslation\Translates` trait.
This trait enhances your model and alters its inner workings to allow you to store translations for them, and minimally affect the way
you use the models. 

All of the storing and accessing translations is done automatically, using the code implemented within the trait. All you have to do is specify
the language you want your model to be in, and everything else will happen on its own.

### Choosing a language for a model

To specify a language on the model, you can simply use the `setLanguage()` method like this:

```php
$article = Article::find($id);
$article->setLanguage('ru');

$article->title; // Returns the title of the article in Russian
```

If, however, you don't feel like setting a language explicitly, the trait will automatically detect the app locale and use it as the active language.

```php
$article = Article::find($id);
App::setLocale('nl');

$article->title; // Returns the article title in Dutch 
```

**Note:** it does not matter whether you set the locale before or after loading the model, it will detect the locale in both scenarios.
The locale will not be used only if you explicitly set a language.

##### Choosing a language for a collection of models

To set a language on a collection of models, you may simply invoke the `setLanguage()` method.

```php
$articles = Article::all();
$articles->setLanguage('fr');

$articles->first()->title; // Returns the first article's title in French
```

This method is designed to remove the N queries problem and is supposed to send a single request to the persistent storage where the translations are.
However, that depends on the driver itself and sometimes it is impossible to achieve the desired effect with a single query 
(i.e. the translations are stored in multiple files on the local filesystem.)

If you find yourself in a situation where you want to set a language on a collection of models without loading the translations themselves, you may pass
`false` as the second argument to the `setLanguage()` call. 

```php
$articles = Article::all();
/*
* Set French as the selected language on all the models in the colleciton, but do not load the translations themselves.
*/
$articles->setLanguage('fr', false);
```

**Note:** this approach can be good for deleting or updating models, but it is not good for reading them. If you try to access any of the translated attributes
later in your code they will automatically be loaded and you will end up sending N requests to the permanent storage. 

**It is advised to always call the `setLanguage()` method on a collection of models if you know you are going to need the translations later in your code.**


### Storing translations

The goal of this package is to stay true to Laravel's core syntax and API as much as possible. With this in mind, all you need to do in order to save your model's
translations is simply choose the language, and the trait will do all the work for you.

#### Making attributes translatable

In order for the package to work, and be able to differentiate your model's base attributes from the attributes you want to make translatable,
you have to create a `$translatable` array on your model which holds the names of the attributes that should be translated.

```php
class Article extends Model
{
    use MisaNeopix\LaravelModelTranslation\Translates;

    protected $translatable = [
        'title', 'body'    
    ];
}
``` 

Bear in mind that in the current version of the package, the `$translatable` attributes **will not be stored with the model's base attributes**. 
The scenario in which that would be possible is the one in which you are using your own driver with such an implementation, but that would
have to be done on the driver level.

#### Persisting translations upon model creation

As mentioned earlier, this package takes automation seriously and aims to do all the work in the back, without worrying you about all the details.

That being said, when creating a new model, all you have to do is set the app locale prior to creating your model, pass the translations along 
with your base attributes and watch the magic happen. It's that simple.

```php
App::setLocale('rs');
$article = Article::create([
    'title' => 'Naslov na srpskom', // These two attributes will be stored as translations
    'body' => 'Sadržaj članka',     // because they are present in the $translatable array
    'author' => 'Miša Ković'        // But this one will be stored as a base attribute
]);

App::setLocale('en');

$anotherArticle = Article::make();

$article->title = 'Title in English'; // This two attributes will be stored as translations here too
$article->body = 'Article content';   // Also because they are present in the $translatable array
$article->author = 'Misha Kovic';     // And this one will find its way into the model's base attributes

$article->save();
```

#### Persisting translations on existing models

To store translations in a particular language, simply call the `setLanguage()` method on your model and do everything else as you would with the rest of your attributes.

```php
$article = Article::find($id);
$article->setLanguage('en');

$article->title = 'Breaking News: Awesome New Laravel Package Is Now Available';
$article->save();
```

**Note:** when storing translations you may not call the `setLanguage()` method after setting the translations but before persisting them. If you did,
you would lose all the changes you made since the newly selected language's translations will be loaded into the model automatically.

```php
$article = Article::find(1);

$article->setLanguage('en');
$article->title = "I'm about to get overriden";

$article->setLanguage('de'); // At this point we are overriding the previously set title and losing the change.
```

As with reading attributes, if you do not set a language explicitly, the `$translatable` attributes will still be treated in the same way, i.e. they will still 
be saved as translations and the current app locale will be used as the model's language.

In this case it is also irrelevant where you change the app locale, as long as you do it before persisting the changes.

```php
$article = Article::find($id);
$article->title = "I'm not going anywhere";

App::setLocale('en');

$article->save();
```

The order of execution below will produce the same outcome:

```php
$article = Article::find($id);

App::setLocale('en');

$article->title = "I'm not going anywhere either!";
$article->save();
``` 

It also does not matter if the locale was set even before loading the model, hence this snippet has the same result:

```php
App::setLocale('en');

$article = Article::find($id);
$article->title = "I'm safe, nothing to worry about";
$article->save();
```

Translatable attributes are subject to Laravel's mutator methods just like any other attributes. Having a `setTitleAttribute()` method is still possible 
and allows you to transform the attributes before storing them as translations. The fact that an attribute is translatable should in no way alter the structure of the mutator, 
you should implement it and store the value in the `$attributes` array as you would with any other mutator.

```php
class Article extends Model
{
    use MisaNeopix\LaravelModelTranslation\Translates;
    
    public function setTitleAttribute($originalTitle)
    {
        $this->attributes['title'] = ucwords($originalTitle);
    }
}
```

When using Laravel's `fill()` method, the translations will still be handled as they would with manual assignment. 

However, in order to be mass-assigned, the `$translatable` attributes have to be inside the `$fillable` array to. Being Laravel's native feature, the `$fillable`
array takes precedence over the `$translatable` and if the attribute is not marked as fillable, it will not be saved during mass-assignment despite being marked as `$translatable`.

```php
$article = Article::find($id);
$article->setLanguage('rs');

/*
* This will store the translation in Serbian provided the title attribute is both translatable and fillable
*/ 
$article->fill([
    'title' => 'Naslov na srpskom'
]);
$article->save();

/*
* This will behave the same way
*/
$article->update([
    'body' => 'Sadržaj članka na srpskom'
]);
```

### Deleting translations

When deleting a model all the translations will be deleted along with it. The package is careful, though, and if your model utilizes soft-deleting, the translations will not be
deleted until your model is fully deleted too, keeping the translations in store in case you need to restore your model later. 

Deleting the translations along with your model requires no work additional to deleting the model itself;

```php
App::setLocale('en');

$article = Article::first();
$article->update([
    'title' => 'Title in English', // Store the title and body translations
    'body' => 'Body in English'    // in your selcted driver's permanent storage
]);

$article->delete(); // Delete the translations too (provided the Article model does not encorporate soft-deleting)

Translation::getTranslationsForModel($article, 'en'); // returns an empty array
```

In addition to deleting the complete model with all of its translations, you are also able to delete the translations explicitly, without deleting the model.
You may achieve this by utilizing the `deleteLanguages()` method, the one that is also used upon model deletion. Optionally, you may pass in the codes of the languages that you want to delete, provided you
don't want to delete all the languages but rather select ones.

```php
/*
* Let's imagine that this article is available in 'en', 'rs', 'es', 'ru', 'nl', 'bg'
*/
$article = Article::first();

$article->deleteTranslations('en', 'nl'); // Deletes all the translations in English and Dutch for this article

$article->deleteTranslations(['rs', 'ru']); // Deletes all the Serbian and Russian translations leaving only the Spanish and Bulgarian

$article->deleteTranslations(); // Delete all the languages, in this case Spanish and Bulgarian
``` 

**Note:** when explicitly deleting translations you have full control and the trait will not check for soft-deletion, but delete the translations no matter what.
It is your responsibility when calling the `deleteTranslations()` method to keep track of which data you are erasing.

### Other capabilities

#### Getting the currently selected language

If you need to get the language your model is currently using you may access the `active_langauge` dynamic property. This property will have either the value of the language code
currently set on the model, or the value of the current app locale. In other words, it will return exactly the code of the language it would return the translations for.

```php
App::setLocale('en');
$article = Article::first();
$article->active_language; // Returns 'en'

$article->setLanguage('bg');
$article->active_language; // Returns 'bg'
```

#### Getting an array of all the available languages for a model instance

It is also possible to obtain an array of all the languages your model has translations in. To get this value simply access the `available_languages` dynamic property and you will
get an array containing  the codes of all the languages your model instance has translations in. However, when using this property keep in mind that it queries the persistent translation driver 
on each call in order to maintain the integrity of the result.

```php
// Let's imagine that we have this article in 'en', 'rs' and 'bg'
$article = Article::first();
$article->available_languages; // returns ['en', 'rs', 'bg']

$article->deleteLanguage('bg');

$article->available_languages; // returns ['en', 'rs']
```

**Note:** both `active_language` and `available_languages` are implemented as Laravel's accessors. This is done so it is possible to add those dynamic properties to the `$appends` array on your model 
and have them automatically loaded upon each serialization. However, with 'available_languages' this would cause the N + 1 queries problem when serializing a collection of models, depending on the driver you're using.

#### Getting models available in a particular language

It is intuitive that the opposite of loading all languages for a model it is possible to load all models for a particular language. This is possible by using a custom query scope on your model loading query
called `inLanguage()`. This scope takes a single parameter, the code of the language you want to load the models for, and ensures that all the models returned by the query are available in the provided language.
Keep in mind that this **does not load the translations**, but merely constrains your query.

```php
$articles = Article::inLanguage('rs')->get(); // Returns only the articles that are available in Serbian
```

## Driver-based approach

As mentioned previously, this package is implemented through a driver-based approach, meaning that it is architecture agnostic and can very easily be adapted to any permanent storage system you are using.
The idea of the package is to enhance Laravel's current syntax and expose API's for fluently storing model translations, but at the same time provide enough flexibility to be compatible with any architecture
and not impose any technologies nor dependencies.

As mentioned in the introduction, this package comes prepacked with two drivers, MySQL and JSON. As the names suggest, the MySQL driver employs a MySQL database for persistently storing translation, and the JSON 
driver stores the translations in JSON files in a predefined structure on the filesystem.

### The JSON driver

The JSON driver is the simplest one to use as it requires no setup whatsoever. It is preconfigured as the default driver, and after installing the package via Composer you may instantly inject the 
`MisaNeopix\LaravelModelTranslation\Translates` trait into the models you want to make translatable and use it.  

By default, it will store the translation files and folders in your `storage/app/translations` directory, but you are free to change this setting in the configuration file.
It is advised that you do not stray from your `storage/app` folder when storing translations as this could provoke unnecessary implications, 
however if you know what you are there are no restrictions in the package that would stop you.  

```php
/*
 * The configuration for the 'json' Laravel Model Translation driver.
 */
'json' => [

    /*
     * The path where the translation JSON's should be stored
     */
    'base_path' => storage_path('app/translations') // Specify your desired location on the filesystem here

],
```

### The MySQL driver

The MySQL driver is simple to use and is really simple to setup in applications which already contain a MySQL database. Before using this driver you are required to publish and then run the migrations that come with this package.
To publish the migrations simply call the following command:

```bash
php artisan vendor:publish --provider="MisaNeopix\LaravelModelTranslation\ModelTranslationServiceProvider" --tag=migrations
```

After running this command you should have a new migration in your migrations folder with this structure:

```php
<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTranslationsTable extends Migration
{
    /**
     * Run the migrations and create the translations table.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('translations', function(Blueprint $table) {
            $table->increments('id');

            $table->string('language');
            $table->string('translatable_type');
            $table->string('translatable_id');

            $table->string('name');
            $table->string('value');
        });
    }

    /**
     * Reverse the migrations and drop the translations table.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('translations');
    }
}
```

It is highly advised that you **do not change the table structure** as that could potentially cripple the driver and make it non-functional. However, you are free to change the name of the table used for 
storing translations, but if you decide to do that you also have to input the same table name into the `config/translations.php` file.

```php
/*
         * The configuration for the 'mysql' Laravel Model Translation driver.
         * Only change this if you alter the migration published with the package.
         */
        'mysql' => [

            /*
             * The name of the table in which the translations are stored.
             */
            'table' => 'translations' // <--The name of the table goes here--
        ]
``` 

Of course, the final step of this process is to run the migrations.

After completing all of the steps above you are able to use your models as you would per the instructions above, and all the translations will be handled and will live happily in your database.


### Creating your own drivers

Creating a driver-based approach without a way to create and plug your own drivers in would be meaningless. With this package it is extremely easy to create, plugin and test your custom drivers.  

#### Implementing the contract

All that it takes to create your own driver is that you implement the `MisaNeopix\LaravelModelTranslation\Contracts\TranslationDriver` contract and extend the 
`MisaNeopix\LaravelModelTranslation\TranslationManager` with your shiny new translation driver.

All the methods the contract imposes are thoroughly described in the contract itself, but here is a list of the methods you need to implement:

+ `storeTranslationsForModel(Model $model, string $language, array $translations): bool`
    + This method is mostly used for testing purposes and as an internal helper method in some of the first-party drivers.
    This method needs to unconditionally store the provided translations for the provided language, without performing any checks.
    It should return `true` on success and `false` if it failed.
    
+ `getTranslationsForModel(Model $model, string $language): array`
    + This one returns an array of all the available translations in the provided language for the provided model.
    It needs to return an associative array where the keys are names of the translated attributes and the values are the translations themselves
    ```php
    [
        'title' => 'Title in English',
        'body' => 'Content of the article'
    ]
    ``` 
    
+ `getTranslationsForModels(Collection $models, string $language): array`
    + This one is a little tricky. It is meant to do the same as the previous method, but for multiple models. The point of this method is to avoid the N queries problem, but that may be impossible depending 
    on your architecture. Its return value is also required to be in this format:
    ```php
    [
        /*
        * The first level keys are ID's of the models they belong to
        * The second level keys are names of the translated attributes
        */
        1 => [
            'title' => 'Title in English'
        ],
        7 => [
            'title' => 'English Title'
        ]
    ]
    ```
+ `getAvailableLanguagesForModel(Model $model): array`
    + This methods returns an array of all the languages available for the provided model. If the model has at least a single translation
    in a particular language, that language should be part of the returned array. The array should be a simple array of language codes in the form of strings. 
    
+ `getModelsAvailableInLanguage(string $modelIdentifier, string $language): array`
    + This method is in a way opposite of the `getAvailableLanguagesForModel()` as it returns an array of ID's of all the model instances that have translations in the provided language.
    The first argument defines which model the returned instances should belong to. By default settings, this will be the fully qualified name of the model. The second argument defines the language
    returned instances should be available in.
    
+ `putTranslationsForModel(Model $model, string $language, array $translations): bool`
    + This method is meant for upserting translations for a designated model in the designated language. It is supposed to persistently store the provided translations and remove all other translations for the 
    provided model in the provided language. In other words, all the available translations for the provided model in the provided language should be those from the `$translations` parameter and no other.
    It should return `true` on success and `false` if it failed. 
    
+ `patchTranslationsForModel(Model $model, string $language, array $translations): bool`
    + This method should also perform an upsert-like operation, but unlike the previous one it should only affect the translations present in the `$translations` parameter. 
    It should only edit the ones present in both the parameter and the storage, and create the ones present in the parameter but not the storage, and not deal with any other translations.
    It should return `true` on success and `false` if it failed. 
    
+ `deleteAllTranslationsForModel(Model $model): bool`
    + This one is rather straightforward. It should simply delete all the translations available for the provided model instance.
    It should return `true` on success and `false` if it failed.
    
+ `deleteLanguagesForModel(Model $model, array $languages): bool`
    + This one is similar as the previous method, except that it constrains the deletion to the specified languages. Namely, this method should delete all the translations for the provided model in the provided language.
    It should return `true` on success and `false` if it failed. 

+ `deleteAttributesForModel(Model $model, array $attributes, string $language = null): bool`
    + This deletes only the particular attributes for the provided model. The attributes that should be deleted are present in the `$attributes` parameter in the form of an array with attribute names as strings.
    It should be possible to additionally constrain the deletion by passing in a language, in which case the method should still delete only the provided translations, but only in the provided language.
    It should return `true` on success and `false` if it failed. 


#### Model instance identification

Since the driver should be model-agnostic, i.e. the driver should be able to store translations for all of your models, it needs a way to distinguish model instances not on the instance level, but on the class level as well.
For an example, if we wanted to store translations for an `App\Models\Article` instance with the ID of 3, and an `App\Models\Category` model with the ID of 3 as well, it would not be possible to identify them simply by their ID, we would need to be aware
that one set of translations belongs to the article with the ID of 3, and the other to the category with the same ID. 

In order to solve the previously illustrated problem, the `Translates` trait that translatable models should use exposes the `getModelIdentifier()` and `getInstanceIdentifier()` calls which should be used by drivers to uniquely and uniformly identify model instances.
These calls should be used in almost every method of your driver as they ensure the integrity and simplicity of unique identification of your models. 
In order to better understand how you can utilize these methods it is best that you get familiar with the implementation of the existing drivers, especially the `JSONTranslationDriver`.

#### Registering custom drivers

Once you implement your driver, you must register it with the package in order to use it. To register a package, within a service provider simply call the `Translation::extend($abstract, $callback)` method and pass in the abstract name of the driver and a callback function 
which should return an instance of your driver. The callback function should accept an instance of Laravel's service container as its only parameter.

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\TranslationDrivers\MongoTranslationDriver;
use MisaNeopix\LaravelModelTranslation\Translation;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        /*
         * Imagine that we have a driver called MongoTranslationDriver in the App\TranslationDrivers namespace
         */
        Translation::extend('mongo', function ($app) {
            return $app->resolve(MongoTranslationDriver::class);
        });
    }
}
``` 

#### Testing custom drivers

The package comes with a test suite for testing custom drivers. It relies on Laravel's Artisan CLI and PHPUnit to test your custom drivers.
The package includes an Artisan command which gathers all of the custom drivers you registered and then starts a testsuite for testing all the packages you have.

To start these tests simply run the following Artisan command:

```php
php artisan translation:test-extensions
``` 

You are able to optionally pass in the abstract names of the drivers you want to test, however if you do not pass any parameters, the command will test all of the custom drivers you have registered.

```php
php artisan translation:test-extensions textfiles
```

If your drivers do not utilize a database, or for whatever the reason, you do not have a database set up, you may include the `--no-database` option in your command call which will ensure that you do not receive
an error about attempting to communicate with a database that doesn't exist.

```php
php artisan translation:test-extensions textfiles --no-database
```

This command proxies the testing call to PHPUnit and proxies the full output from PHPUnit back to you. If any of the tests fail, the test suite is equipped with 
detailed failure messages to help you realize where your driver is not behaving as desired.

**This command is the best way to ensure that your driver is compatible with the rest of the package architecture and will not break its behaviour.**

#### Using custom drivers

After having implemented, registered and tested your custom drivers, you are finally ready to see it in action.
To use your driver in combination with the `Translates` trait and have all the capabilities listed in the Usage page, all you have to do is specify your driver's abstract name as the default driver in the config.
 
```php
return [

        /*
         * The default driver to be used.
         * Out of the box, Laravel Model Translation comes with the 'mysql' and 'json' drivers
         */
        'driver' => 'mongo',
```

This will make the manager use your driver as the default, and the trait will do all the work in communicating with your driver automatically. 
If you wanted to change your driver on the fly, you may resort to the `Translation::setDefaultDriver()` method on the `Translation` facade. This method accepts the driver's abstract name as its default parameter.

Of course, you are not limited to using drivers only through the your models. You may call any of the driver's methods on the `Translation` facade, and they will be proxied to your default driver.


## Testing

The package comes with built-in utilities for testing your code and this package. 

If you do not want to use your persistent storage mechanisms for test, you may use the `Translation::fake()` method which stores your translations during a single test but erases them after it has completed,
and also doesn't rely on any external permanent storage systems but keeps everything in memory.

```php
use Tests\TestCase;
use MisaNeopix\LaravelModelTranslation\Translation;

class OmniTest extends TestCase
{
    public function test_everything()
    {
        Translation::fake();
        /*
        * The rest of the test
        */    
    }
}
```

If you need to test whether models have or haven't got some translations, you may exploit one of the following methods:
+ `Translation::assertModelHasTranslation(Model $model, string $attribute, string $language)`
    + This asserts that the provided model has a translation for the provided attribute in the provided language
    ```php
  Translation::fake();
  $article = Article::create();
  $article->setLanguage('en');
  $article->update(['title' => 'Title in English']);

  Translation::assertModelHasTranslation($article, 'title', 'en');
    ``` 
+ `Translation::assertNotModelHasTranslation(Model $model, string $attribute, string $language)`
    + This method asserts that a model doesn't have a translation for the provided attribute in the provided language.
    ```php
    Translation::fake();
    $article = Article::create();
  
    Translation::assertNotModelHasTranslation($article, 'title', 'en');
    ```
+ `Translation::assertModelTranslation(Model $model, string $attribute, string $language, string $expected)`.
    + This asserts that the given model's provided attribute's translation in the provided language is equal to the provided expected value.
    
**Note:** assertion methods are available only after calling the `Translation::fake()` method.