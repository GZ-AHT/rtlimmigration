<?php

namespace Tests\Feature;

use Database\Seeders\WebsiteContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_a_successful_response()
    {
        Storage::fake('public');
        $this->seed(WebsiteContentSeeder::class);

        $response = $this->get(route('home'));

        $response->assertOk();
    }
}
