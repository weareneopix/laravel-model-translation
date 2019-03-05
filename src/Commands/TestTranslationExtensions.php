<?php

namespace WeAreNeopix\LaravelModelTranslation\Commands;

use Illuminate\Console\Command;

class TestTranslationExtensions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translation:test-extensions 
                                                {extensions?*  : The extensions that should be tested} 
                                                {--no-database : Defines whether the tests should be performed without access to a database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test if the registered TranslationManager extensions work in the way they are supposed to.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->info('Testing custom Translation drivers.');
        $drivers = $this->prepareExtensions();
        $this->info('These drivers are being tested: '.implode(', ', $drivers));

        system($this->makeCommand());
    }

    /**
     * Compose the phpunit command to be ran in order to perform the extension tests.
     *
     * @param string|null $extensionToRun
     * @return string
     */
    protected function makeCommand()
    {
        $phpUnitPath = base_path('vendor/bin/phpunit');
        $autoloadPath = base_path('vendor/autoload.php');
        $phpunitXmlPath = $this->getPhpunitXmlPath();
        $testFilePath = $this->getTestFilePath();

        $command = "{$phpUnitPath} --bootstrap {$autoloadPath} --configuration {$phpunitXmlPath} {$testFilePath} ";

        if ($this->option('no-database') === true) {
            $command .= '_test_without_database_ ';
        }

        $command .= implode(' ', $this->prepareExtensions());

        return $command;
    }

    /**
     * Returns the path to the phpunit.xml configuration file.
     *
     * @return string
     */
    protected function getPhpunitXmlPath()
    {
        return base_path('packages/misa-neopix/laravel-model-translation/tests/extensions/phpunit.xml');
    }

    /**
     * Return the path to the file to be tested.
     * We need to include this file manually
     * in order to provide driver names that are to
     * be tested after it.
     *
     * @return string
     */
    protected function getTestFilePath()
    {
        return base_path('packages/misa-neopix/laravel-model-translation/tests/extensions/UserExtensionsTest.php');
    }

    /**
     * We check if the user has provided any extensions here.
     * If there are any provided extensions, we will use only
     * those.
     * Otherwise, we will fetch all the registered
     * extensions from the TranslationDriver and
     * test them all.
     *
     * @return array
     */
    protected function prepareExtensions()
    {
        $specifiedExtensions = $this->argument('extensions');

        if (! empty($specifiedExtensions)) {
            return $specifiedExtensions;
        }

        return $this->getLaravel()['translation']->getRegisteredExtensionNames();
    }
}
