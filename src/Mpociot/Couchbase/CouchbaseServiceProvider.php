<?php namespace Mpociot\Couchbase;

use Illuminate\Support\ServiceProvider;
use Mpociot\Couchbase\Eloquent\Model;

class CouchbaseServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap events and ensure couchbase config sanity
     */
    public function boot()
    {
        // TODO: Support other serialization and decoder types.
        ini_set('couchbase.serializer', 'json');
        ini_set('couchbase.decoder.json_arrays', 0);

        Model::setConnectionResolver($this->app['db']);
        Model::setEventDispatcher($this->app['events']);
    }

    /**
     * Register the service provider.
     */
    public function register()
    {
        // Add database driver.
        $this->app->singleton('couchbase.connection',
            function ($app) {
                $connectionName = config('database.connections.' . config('database.default'));
                return new Connection($connectionName);
            });
        $this->app->resolving('db',
            function ($db) {
                $db->extend('couchbase',
                    function ($config) {
                        return app('couchbase.connection');
                    });
            });
    }
}
