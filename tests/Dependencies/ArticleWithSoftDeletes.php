<?php

namespace WeAreNeopix\LaravelModelTranslation\Test\Dependencies;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use WeAreNeopix\LaravelModelTranslation\Translates;

class ArticleWithSoftDeletes extends Model
{
    use Translates, SoftDeletes;

    protected $table = 'articles';

    protected $fillable = [
        'published_at', 'author',
    ];

    protected $translatable = [
        'title', 'body', 'description',
    ];
}
