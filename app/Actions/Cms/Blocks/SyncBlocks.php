<?php

declare(strict_types=1);

namespace App\Actions\Cms\Blocks;

use App\Models\Cms\Block;
use App\Services\Cms\Blocks\BlockRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Replaces the ordered block list of a page or post. Payloads are
 * validated against their block type's rules before anything persists.
 */
class SyncBlocks
{
    public function __construct(private readonly BlockRegistry $registry) {}

    /**
     * @param  Model  $owner  A model using the HasBlocks concern.
     * @param  list<array{id?: int|null, type: string, payload: array<string, mixed>, is_visible?: bool}>  $blocks
     */
    public function handle(Model $owner, array $blocks): void
    {
        $payloads = $this->validate($blocks);

        /** @var MorphMany<Block, Model> $relation */
        $relation = $owner->blocks(); // @phpstan-ignore method.notFound

        // Fetched once: calling find() on $relation per-block would keep
        // adding `where id = ?` clauses to its shared query builder,
        // making every lookup after the first unmatchable.
        $existingBlocks = $relation->get()->keyBy('id');

        $keptIds = [];

        foreach ($blocks as $position => $data) {
            $attributes = [
                'type' => $data['type'],
                // Only validated keys persist — client-side extras are dropped.
                'payload' => $payloads[$position],
                'position' => $position,
                'is_visible' => $data['is_visible'] ?? true,
            ];

            $existing = isset($data['id']) ? $existingBlocks->get($data['id']) : null;

            if ($existing instanceof Block) {
                $existing->update($attributes);
                $keptIds[] = $existing->id;
            } else {
                $keptIds[] = $relation->create($attributes)->id;
            }
        }

        $relation->whereKeyNot($keptIds)->delete();
    }

    /**
     * @param  list<array{id?: int|null, type: string, payload: array<string, mixed>, is_visible?: bool}>  $blocks
     * @return array<int, array<string, mixed>> Validated payload per block position.
     */
    private function validate(array $blocks): array
    {
        [$payloads, $errors] = $this->validateList($blocks, 'blocks');

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $payloads;
    }

    /**
     * Recursively validates a (possibly nested, for container blocks) list
     * of blocks, returning validated payloads and any accumulated errors
     * keyed with dotted paths relative to the request root. Unlike
     * handle()'s top-level $blocks (shaped by the FormRequest envelope
     * rules), nested children are untrusted/unshaped data extracted from a
     * parent's JSON payload, so this accepts a looser array shape.
     *
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array{0: array<int, array<string, mixed>>, 1: array<string, array<int, string>>}
     */
    private function validateList(array $blocks, string $prefix): array
    {
        $errors = [];
        $payloads = [];

        foreach ($blocks as $index => $data) {
            $type = $data['type'] ?? null;

            if (! is_string($type) || ! $this->registry->has($type)) {
                $errors["{$prefix}.{$index}.type"] = ['Unknown block type.'];

                continue;
            }

            $blockType = $this->registry->get($type);

            $validator = Validator::make(
                $data['payload'] ?? [],
                $blockType->rules(),
            );

            if ($validator->fails()) {
                foreach ($validator->errors()->messages() as $key => $messages) {
                    $errors["{$prefix}.{$index}.payload.{$key}"] = $messages;
                }

                continue;
            }

            $validated = $validator->validated();

            if ($blockType->supportsChildren()) {
                $payload = $data['payload'] ?? [];
                $rawChildren = is_array($payload['blocks'] ?? null) ? $payload['blocks'] : [];

                foreach ($rawChildren as $childIndex => $child) {
                    if (($child['type'] ?? null) === 'section') {
                        $errors["{$prefix}.{$index}.payload.blocks.{$childIndex}.type"] = ['Sections cannot be nested inside another section.'];
                    }
                }

                [$childPayloads, $childErrors] = $this->validateList($rawChildren, "{$prefix}.{$index}.payload.blocks");
                $errors = [...$errors, ...$childErrors];

                $validated['blocks'] = collect($rawChildren)
                    ->map(fn (array $child, int $childIndex): array => [
                        'type' => $child['type'] ?? null,
                        'payload' => $childPayloads[$childIndex] ?? [],
                        'is_visible' => (bool) ($child['is_visible'] ?? true),
                    ])
                    ->values()
                    ->all();
            }

            $payloads[$index] = $validated;
        }

        return [$payloads, $errors];
    }
}
