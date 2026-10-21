<?php

declare(strict_types=1);

namespace App\Actions\AI;

use App\Models\CallTranscript;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerMessage;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\Part;
use App\Models\Tag;
use App\Models\WorkOrder;
use App\Models\WorkOrderNote;
use App\Platform\AI\AI;
use App\Platform\AI\Assistant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * SOW §3.1 "global search across customers, jobs, and work order notes" +
 * §3.16 "AI-assisted search": the assistant turns the question into a plan
 * (keywords, entity types, numbers, tags), we run tenant-scoped queries, and
 * the assistant writes a one-line grounded answer over the hits.
 *
 * @phpstan-type Hit array{type: string, title: string, snippet: string, url: string, when: \Illuminate\Support\Carbon|null, tags: list<string>}
 */
final class SearchEverything
{
    use AsAction;

    public const array TYPES = ['customer', 'contact', 'job', 'work_order', 'note', 'part', 'invoice', 'message', 'call'];

    /** @return array{plan: array<string, mixed>, hits: list<Hit>, answer: string, provider: string} */
    public function handle(string $question, bool $fieldUser = false, int $limit = 40): array
    {
        $question = trim($question);
        if ($question === '') {
            return ['plan' => [], 'hits' => [], 'answer' => '', 'provider' => AI::assistant()->name()];
        }

        $plan = AI::run('search', fn (Assistant $a) => $a->planSearch($question, self::TYPES));
        $terms = array_values(array_unique(array_merge($plan['keywords'], $plan['numbers'])));
        if ($terms === [] && $plan['tags'] === []) {
            $terms = [$question];
        }

        $hits = collect();
        $types = $plan['entities'] ?: self::TYPES;
        $tagIds = $plan['tags'] !== [] ? Tag::query()->whereIn('slug', array_map(Tag::normalize(...), $plan['tags']))->pluck('id')->all() : [];

        if (! $fieldUser && in_array('customer', $types, true)) {
            foreach ($this->like(Customer::query(), ['name', 'phone', 'email', 'notes'], $terms)->take(10)->get() as $c) {
                $hits->push($this->hit('customer', $c->name, trim(($c->phone ?? '').' '.($c->email ?? '')), route('contacts.show', $c), $c->updated_at));
            }
        }
        if (! $fieldUser && in_array('contact', $types, true)) {
            foreach ($this->like(Contact::query()->with('customer'), ['name', 'phone', 'email', 'role'], $terms)->take(10)->get() as $c) {
                $hits->push($this->hit('contact', $c->name.' · '.($c->customer->name ?? ''), trim(($c->role ?? '').' '.($c->phone ?? '')), $c->customer ? route('contacts.show', $c->customer) : route('contacts.index'), $c->updated_at));
            }
        }
        if (in_array('job', $types, true)) {
            $q = $this->like(Job::query()->with('customer'), ['number', 'title'], $terms);
            foreach ($q->take(10)->get() as $j) {
                $hits->push($this->hit('job', ($j->number ? $j->number.' · ' : '').$j->title, $j->customer->name ?? '', route('jobs.show', $j), $j->updated_at));
            }
        }
        if (in_array('work_order', $types, true) || in_array('note', $types, true)) {
            $q = WorkOrder::query()->with(['job.customer', 'tags'])
                ->where(function (Builder $w) use ($terms, $tagIds, $types) {
                    if (in_array('work_order', $types, true)) {
                        $this->orLike($w, ['number', 'customer_notes', 'internal_notes', 'scope_office', 'work_performed', 'work_type'], $terms);
                        $w->orWhereHas('job', fn ($j) => $this->orLike($j, ['title', 'number'], $terms)->orWhereHas('customer', fn ($c) => $this->orLike($c, ['name'], $terms)));
                    }
                    if (in_array('note', $types, true)) {
                        $w->orWhereHas('notes', fn ($n) => $this->orLike($n, ['body'], $terms));
                    }
                    if ($tagIds !== []) {
                        $w->orWhereHas('tags', fn ($t) => $t->whereIn('tags.id', $tagIds));
                    }
                });
            foreach ($q->latest()->take(15)->get() as $wo) {
                $note = in_array('note', $types, true) ? WorkOrderNote::query()->where('work_order_id', $wo->id)->where(fn ($n) => $this->orLike($n, ['body'], $terms))->latest()->first() : null;
                $hits->push($this->hit('work_order', ($wo->number ?? 'WO #'.$wo->id).' · '.($wo->job->customer->name ?? ''), $note ? 'Note: '.Str::limit($note->body, 120) : Str::limit($wo->customer_notes ?? $wo->job->title ?? '', 120), route('work-orders.show', $wo), $wo->updated_at, $wo->tags->pluck('slug')->all()));
            }
        }
        if (in_array('part', $types, true)) {
            foreach (Part::query()->search(implode(' ', $terms) ?: $question)->take(8)->get() as $p) {
                $hits->push($this->hit('part', $p->name, trim(($p->sku ?? '').' · '.implode(', ', $p->nicknames ?? [])), route('parts.show', $p), $p->updated_at));
            }
            if ($hits->where('type', 'part')->isEmpty()) {
                foreach ($terms as $t) {
                    foreach (Part::query()->search($t)->take(5)->get() as $p) {
                        $hits->push($this->hit('part', $p->name, trim(($p->sku ?? '').' · '.implode(', ', $p->nicknames ?? [])), route('parts.show', $p), $p->updated_at));
                    }
                }
            }
        }
        if (! $fieldUser && in_array('invoice', $types, true)) {
            foreach ($this->like(Invoice::query()->with('customer'), ['number', 'status'], $terms)->take(10)->get() as $i) {
                $hits->push($this->hit('invoice', $i->number.' · $'.number_format((float) $i->total, 2), ($i->customer->name ?? '').' · '.$i->status, route('invoices.index'), $i->updated_at));
            }
        }
        if (! $fieldUser && in_array('message', $types, true)) {
            foreach ($this->like(CustomerMessage::query()->with('customer'), ['body', 'subject', 'to', 'from'], $terms)->latest()->take(10)->get() as $m) {
                $hits->push($this->hit('message', ($m->customer->name ?? $m->to ?? 'message').' · '.$m->channel, Str::limit($m->body, 120), $m->work_order_id ? route('work-orders.show', $m->work_order_id) : route('messages.index'), $m->created_at));
            }
        }
        if (in_array('call', $types, true)) {
            foreach ($this->like(CallTranscript::query()->with('customer'), ['transcript', 'summary', 'phone'], $terms)->latest('called_at')->take(8)->get() as $c) {
                $hits->push($this->hit('call', 'Call · '.($c->customer->name ?? $c->phone ?? ''), Str::limit($c->summary ?? $c->transcript ?? '', 120), route('messages.calls'), $c->called_at));
            }
        }

        $hits = $hits->unique(fn ($h) => $h['type'].$h['url'].$h['title'])->sortByDesc(fn ($h) => $h['when']->timestamp ?? 0)->take($limit)->values()->all();
        $answer = AI::run('search', fn (Assistant $a) => $a->answerSearch($question, array_map(fn ($h) => ['type' => $h['type'], 'title' => $h['title'], 'snippet' => $h['snippet'], 'url' => $h['url']], array_slice($hits, 0, 12))));

        return ['plan' => $plan, 'hits' => $hits, 'answer' => $answer, 'provider' => AI::assistant()->name()];
    }

    /** @return Hit */
    private function hit(string $type, string $title, string $snippet, string $url, $when, array $tags = []): array
    {
        return ['type' => $type, 'title' => $title, 'snippet' => $snippet, 'url' => $url, 'when' => $when, 'tags' => $tags];
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $q
     * @param  list<string>  $columns
     * @param  list<string>  $terms
     * @return Builder<TModel>
     */
    private function like(Builder $q, array $columns, array $terms): Builder
    {
        return $q->where(fn (Builder $w) => $this->orLike($w, $columns, $terms));
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $w
     * @param  list<string>  $columns
     * @param  list<string>  $terms
     * @return Builder<TModel>
     */
    private function orLike(Builder $w, array $columns, array $terms): Builder
    {
        foreach ($terms as $t) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $t).'%';
            foreach ($columns as $c) {
                $w->orWhere($c, 'like', $like);
            }
        }

        return $w;
    }
}
