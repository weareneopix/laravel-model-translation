<?php

namespace WeAreNeopix\LaravelModelTranslation\Commands;

use Illuminate\Console\GeneratorCommand;

class MakeDriverCommand extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translation:make-extension 
                                {name : Name of the extension to be created}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a new TranslationDriver class.';

    protected $type = 'TranslationDriver';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        parent::handle();
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return __DIR__ . '/../../stubs/driver.stub';
    }

    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace . '\TranslationDrivers';
    }
}
