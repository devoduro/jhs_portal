<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // No test may ever reach a real SMS gateway (it spends real credit and texts real numbers):
        // any outgoing request a test has not faked itself is blocked. Tests that care about a
        // gateway's reply call Http::fake([...]) as usual.
        Http::swap(new Factory());
        Http::preventStrayRequests();
    }
}
