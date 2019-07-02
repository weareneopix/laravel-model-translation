<?php

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
             * The path where the translation JSON's should be stored
             */
            'base_path' => storage_path('app/translations'),

            /*
             * Define whether you want to cache which Models are available in which languages using the language-model JSON map.
             * This significantly reduces the execution time of the JSONTranslationDriver::getModelsAvailableInLanguage() method
             * and the inLanguage() scope that relies on the before-mentioned method.
             * The cost of this improvement is slightly slower write operations and a meagerly larger storage consumption,
             * however writing to the cache will be queued if you have a queue configured and the increase in storage requirement is negligible.
             */
            'cache' => true,

        ],

        /*
         * The configuration for the 'mysql' Laravel Model Translation driver.
         * Only change this if you alter the migration published with the package.
         */
        'mysql' => [

            /*
             * The name of the table in which the translations are stored.
             */
            'table' => 'translations',
        ],
    ];
