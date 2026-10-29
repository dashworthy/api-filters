<?php

declare(strict_types=1);

namespace Dashworthy\ApiFilters\Tests;

use Dashworthy\ApiFilters\ApiFiltersServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            ApiFiltersServiceProvider::class,
        ];
    }

    /**
     * The suite runs on MariaDB, the database the package ships an adapter
     * for. DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME and DB_PASSWORD point it
     * at a server; phpunit.xml.dist holds the defaults.
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'mariadb');
    }

    /**
     * The workbench models' tables, created once per refresh rather than per
     * test, because MariaDB commits DDL and cannot roll it back.
     */
    protected function defineDatabaseMigrationsAfterDatabaseRefreshed(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('password');
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('sku');
            $table->timestamps();
        });

        Schema::create('line_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id');
            $table->integer('quantity');
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('team_id');
            $table->string('status');
            $table->integer('total');
            $table->string('notes')->nullable();
            $table->string('code');
            $table->boolean('is_priority')->default(false);
            $table->dateTime('placed_at')->nullable();
            $table->string('internal_reference')->nullable();
            $table->geometry('location', subtype: 'point')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('display_name');
            $table->string('email');
            $table->timestamps();
        });
    }
}
