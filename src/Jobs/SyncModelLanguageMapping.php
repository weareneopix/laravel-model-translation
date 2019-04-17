<?php

namespace WeAreNeopix\LaravelModelTranslation\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SyncModelLanguageMapping implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var \Illuminate\Database\Eloquent\Model */
    public $model;

    /** @var string */
    public $language;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Model $model, string $language)
    {
        $this->model = $model;
        $this->language = $language;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Translation::driver('json')->syncModelsForLanguage($this->language, $this->model);
    }
}
