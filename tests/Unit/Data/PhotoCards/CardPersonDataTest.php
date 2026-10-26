<?php

namespace Tests\Unit\Data\PhotoCards;

use App\Data\PhotoCards\CardPersonData;
use PHPUnit\Framework\TestCase;

class CardPersonDataTest extends TestCase
{
    public function test_aliases_are_split_trimmed_deduplicated_and_limited(): void
    {
        $this->assertSame(['পরিমনি', 'Porimoni', 'Pori Moni'], CardPersonData::aliases(' পরিমনি, Porimoni ,,Pori   Moni, Porimoni'));
        $this->assertSame([], CardPersonData::aliases(''));
        $this->assertCount(CardPersonData::MAX_ALIASES, CardPersonData::aliases(implode(',', range(1, 20))));
    }
}
