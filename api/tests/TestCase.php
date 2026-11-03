<?php

namespace Tests;

use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected bool $seed = true;

    protected string $seeder = CurrencySeeder::class;
}
