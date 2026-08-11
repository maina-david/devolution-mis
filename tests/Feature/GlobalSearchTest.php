<?php

namespace Tests\Feature;

use App\Models\County;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_county_user_search_is_limited_to_their_authorized_county(): void
    {
        $authorizedCounty = County::factory()->create(['name' => 'Alpha County', 'code' => 1]);
        County::factory()->create(['name' => 'Alpha Neighbour', 'code' => 2]);
        $user = User::factory()->countyOfficial($authorizedCounty)->create();

        $response = $this->actingAs($user)->getJson(route('search.global', [
            'current_team' => $user->currentTeam->slug,
            'q' => 'Alpha',
        ]));

        $response->assertOk()
            ->assertJsonPath('results.0.id', $authorizedCounty->id)
            ->assertJsonMissing(['title' => 'Alpha Neighbour County']);
    }

    public function test_search_rejects_queries_shorter_than_two_characters(): void
    {
        $user = User::factory()->devolutionAdmin()->create();

        $this->actingAs($user)
            ->getJson(route('search.global', ['current_team' => $user->currentTeam->slug, 'q' => 'a']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('q');
    }

    public function test_search_requires_authentication(): void
    {
        $user = User::factory()->create();

        $this->getJson(route('search.global', ['current_team' => $user->currentTeam->slug, 'q' => 'county']))
            ->assertUnauthorized();
    }
}
