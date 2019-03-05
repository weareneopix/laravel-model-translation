<?php

namespace MisaNeopix\LaravelModelTranslation;

use Illuminate\Support\Facades\Facade;

class Translation extends Facade
{
    /**
     * Returns the facade root abstract name.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'translation';
    }

    /**
     * Use the ArrayTranslationDriver instead of the default driver.
     * Usually used for testing purposes.
     *
     * @return void
     */
    public static function fake()
    {
        static::$app['config']->set('translation.driver', 'array');
    }
}
