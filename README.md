# A robust yet elegant system for translating Eloquent Models

[![Latest Stable Version](https://poser.pugx.org/we-are-neopix/laravel-model-translation/v/stable)](https://packagist.org/packages/we-are-neopix/laravel-model-translation)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Build Status](https://travis-ci.org/weareneopix/laravel-model-translation.svg?branch=master)](https://travis-ci.org/weareneopix/laravel-model-translation)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/weareneopix/laravel-model-translation/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/weareneopix/laravel-model-translation/?branch=master)


This package is meant to simplify the process of making Eloquent models translatable. It's aim is to deviate as little as possible from Laravel's
Eloquent API but still provide a satisfiable level of flexibility. This is achieved by utilizing a driver-based approach to storing translations
and a trait which allows Model instances to seamlessly interact with the translation storage. 

After setting the package up, all that takes to use your models with translations is this:

```php
$post = BlogPost::find(1); // An instance of the BlogPost model

App::setLocale('sr');
$post->title = 'Naslov na srpskom';
$post->save();

$post->setLanguage('en');
$post->title = 'Title in English';
$post->save();

$post->title; // Returns 'Title in English';
$post->setLanguage('sr')->title; // Returns 'Naslov na srpskom'
```

Since this is a driver-based solution, you have the full power to implement the architecture for persistently storing translations yourself. 
Of course, the package comes with two drivers out of the box, JSON and MySQL, but you are free to implement your own drivers and rely on whatever
architecture you prefer.


For a more detailed explanation on how the package works and how to use it, please visit our [wiki pages](https://github.com/misa-neopix/laravel-model-translation/wiki).

