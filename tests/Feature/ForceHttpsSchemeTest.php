<?php

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\URL;

it('forces https scheme in production', function () {
    $app = $this->app;
    $app['env'] = 'production';

    (new AppServiceProvider($app))->boot();

    expect(url('/login'))->toStartWith('https://');
});

it('does not force https scheme outside production', function () {
    $app = $this->app;
    $app['env'] = 'local';

    URL::forceScheme('http');

    (new AppServiceProvider($app))->boot();

    expect(url('/login'))->toStartWith('http://');
});
