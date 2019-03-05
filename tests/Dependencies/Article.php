<?php

namespace WeAreNeopix\LaravelModelTranslation\Test\Dependencies;

use Illuminate\Database\Eloquent\Model;
use WeAreNeopix\LaravelModelTranslation\Translates;

class Article extends Model
{
    use Translates;

    protected $fillable = [
        'id', 'published_at', 'author', 'title', 'body', 'description',
    ];

    protected $translatable = [
        'title', 'body', 'description',
    ];

    public function setTestTranslationAttribute($value)
    {
        $this->attributes['test_translation'] = strrev($value);
    }

    public function getTestTranslationAttribute($value)
    {
        return strtoupper($value);
    }
}
