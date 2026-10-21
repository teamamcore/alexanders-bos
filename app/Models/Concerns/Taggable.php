<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/** SOW §3.16 — anything that can carry tags. */
trait Taggable
{
    /** @return MorphToMany<Tag, $this> */
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable')->withPivot('source')->withTimestamps();
    }

    /**
     * @param  list<string>  $names
     * @return list<string> slugs now on the record
     */
    public function applyTags(array $names, string $source = 'ai'): array
    {
        $ids = [];
        foreach (array_unique(array_filter(array_map(trim(...), $names))) as $name) {
            $tag = Tag::findOrCreateByName($name, $source);
            $ids[$tag->id] = ['source' => $source];
        }
        $this->tags()->syncWithoutDetaching($ids);

        return $this->tags()->pluck('slug')->all();
    }

    public function removeTag(string $slug): void
    {
        $tag = Tag::query()->where('slug', Tag::normalize($slug))->first();
        if ($tag) {
            $this->tags()->detach($tag->id);
        }
    }
}
