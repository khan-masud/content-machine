<?php

namespace Tests\Unit\Support\PhotoCards;

use App\Models\CardPerson;
use App\Models\Workspace;
use App\Support\PhotoCards\CardPeopleMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CardPeopleMatcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_bangla_name_matches_with_a_case_ending(): void
    {
        $workspace = Workspace::factory()->create();
        $person = CardPerson::factory()->for($workspace)->create(['name' => 'পরীমনি', 'aliases' => ['পরিমনি']]);

        $match = (new CardPeopleMatcher)->match($workspace->id, 'চিত্রনায়িকা পরিমনির বাসায় লাগল [[ভয়াবহ আগুন]]');

        $this->assertTrue($match?->is($person));
    }

    public function test_latin_names_match_whole_words_only(): void
    {
        $workspace = Workspace::factory()->create();
        $person = CardPerson::factory()->for($workspace)->create(['name' => 'Ali']);
        $matcher = new CardPeopleMatcher;

        $this->assertNull($matcher->match($workspace->id, 'Alibaba shares fell'));
        $this->assertTrue($matcher->match($workspace->id, 'Why ALI said no')?->is($person));
    }

    public function test_the_longest_name_wins(): void
    {
        $workspace = Workspace::factory()->create();
        CardPerson::factory()->for($workspace)->create(['name' => 'শাকিব']);
        $full = CardPerson::factory()->for($workspace)->create(['name' => 'শাকিব খান']);

        $this->assertTrue((new CardPeopleMatcher)->match($workspace->id, 'শাকিব খানের নতুন সিনেমা')?->is($full));
    }

    public function test_other_workspaces_and_unmentioned_people_do_not_match(): void
    {
        $workspace = Workspace::factory()->create();
        CardPerson::factory()->create(['name' => 'পরীমনি']);
        CardPerson::factory()->for($workspace)->create(['name' => 'শাকিব']);

        $this->assertNull((new CardPeopleMatcher)->match($workspace->id, 'পরীমনির বাসায় আগুন'));
    }
}
