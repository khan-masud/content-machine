<?php

namespace App\Support\PhotoCards;

use App\Models\CardPerson;

/**
 * Finds the photo-library person a card headline names. Bangla names match
 * anywhere, so case endings still count (পরিমনির contains পরিমনি); Latin
 * names match whole words only, so "Ali" does not match "Alibaba". The
 * longest matching name wins, so "Shakib Khan" beats "Shakib".
 */
class CardPeopleMatcher
{
    public function match(int $workspaceId, string $headline): ?CardPerson
    {
        $text = mb_strtolower(str_replace(['[[', ']]'], '', $headline));
        $best = null;
        $bestLength = 0;

        $people = CardPerson::query()->where('workspace_id', $workspaceId)->get();

        foreach ($people as $person) {
            foreach ([$person->name, ...$person->aliases] as $term) {
                $term = mb_strtolower(trim((string) $term));
                $length = mb_strlen($term);

                if ($length < 2 || $length <= $bestLength || ! $this->contains($text, $term)) {
                    continue;
                }

                $best = $person;
                $bestLength = $length;
            }
        }

        return $best;
    }

    private function contains(string $text, string $term): bool
    {
        if (preg_match('/^[\x20-\x7E]+$/', $term) === 1) {
            return preg_match('/(?<![\p{L}\p{N}])'.preg_quote($term, '/').'(?![\p{L}\p{N}])/u', $text) === 1;
        }

        return str_contains($text, $term);
    }
}
