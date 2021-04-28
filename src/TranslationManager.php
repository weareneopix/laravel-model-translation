<?php

namespace WeAreNeopix\LaravelModelTranslation;

use Illuminate\Support\Manager;
use WeAreNeopix\LaravelModelTranslation\Contracts\TranslationDriver;
use WeAreNeopix\LaravelModelTranslation\Drivers\JSONTranslationDriver;
use WeAreNeopix\LaravelModelTranslation\Drivers\ArrayTranslationDriver;
use WeAreNeopix\LaravelModelTranslation\Exceptions\NoDefaultDriverException;
use WeAreNeopix\LaravelModelTranslation\Drivers\MySQL\MySQLTranslationDriver;
use WeAreNeopix\LaravelModelTranslation\Exceptions\InvalidTranslationDriverException;

class TranslationManager extends Manager
{
    /**
     * The default driver to be used.
     *
     * @var string
     */
    protected $defaultDriver;

    /**
     * Create the JSON translation driver.
     *
     * @return \WeAreNeopix\LaravelModelTranslation\Drivers\JSONTranslationDriver
     */
    public function createJsonDriver()
    {
        return $this->container->make(JSONTranslationDriver::class);
    }

    /**
     * Create the MySQL translation driver.
     *
     * @return \WeAreNeopix\LaravelModelTranslation\Drivers\MySQL\MySQLTranslationDriver
     */
    public function createMysqlDriver()
    {
        return $this->container->make(MySQLTranslationDriver::class);
    }

    /**
     * Create the array translation driver.
     *
     * @return \WeAreNeopix\LaravelModelTranslation\Drivers\ArrayTranslationDriver
     */
    public function createArrayDriver()
    {
        return $this->container->make(ArrayTranslationDriver::class);
    }

    /**
     * Returns the default driver's name.
     *
     * @return string
     */
    public function getDefaultDriver()
    {
        if ($this->defaultDriver === null) {
            $configDriver = $this->container['config']['translation.driver'];
            if ($configDriver === null) {
                $message = 'A default translation driver has not been specified.';
                throw new NoDefaultDriverException($message);
            }

            $this->defaultDriver = $configDriver;
        }

        return $this->defaultDriver;
    }

    /**
     * Set the default driver in runtime.
     *
     * @param string $driver
     * @return self
     */
    public function setDefaultDriver(string $driver)
    {
        $this->defaultDriver = $driver;

        return $this;
    }

    /**
     * Returns a list of all the instantiable drivers.
     *
     * @return array
     */
    public function getAvailableDrivers()
    {
        return array_merge(
            [
            'json', 'mysql', 'array',
            ],
            $this->getRegisteredExtensionNames()
        );
    }

    /**
     * Returns an array of all the registered extensions.
     *
     * @return array
     */
    public function getRegisteredExtensionNames()
    {
        return array_keys($this->customCreators);
    }

    /**
     * We override this method to ensure that all the drivers
     * provided by this manager implement the TranslationDriver interface.
     *
     * @param  string|null $driver
     * @throws InvalidTranslationDriverException
     * @return TranslationDriver
     */
    public function driver($driver = null)
    {
        $driverConcrete = parent::driver($driver);

        if (! $driverConcrete instanceof TranslationDriver) {
            $message = 'All translation drivers must implement the TranslationDriver interface.';
            throw new InvalidTranslationDriverException($message);
        }

        return $driverConcrete;
    }
}
