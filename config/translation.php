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
