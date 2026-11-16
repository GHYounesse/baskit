<?php

namespace App\Services\Ai;

use App\Models\Category;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

/**
 * Suggests a product's name, description and category from a few keywords
 * and/or a photo. It only suggests: the admin reviews the form and saves
 * it through the normal validation, nothing is stored here.
 */
class ProductDrafter
{
    public function __construct(private LlmClient $llm) {}

    /**
     * @param  Collection<int, Category>  $categories
     * @return array{name: string, description: string, category_id: int|null}
     *
     * @throws LlmException
     */
    public function draft(?string $keywords, ?UploadedFile $image, Collection $categories): array
    {
        $categoryList = $categories->map(fn ($c) => "{$c->id}: {$c->name}")->implode("\n");

        $system = <<<'TXT'
You write product listings for a small online shop. Reply with ONE JSON object and nothing else, with exactly these keys:
"name": a short, clear product name (max 80 characters, no quotes or marketing hype),
"description": 2 to 4 plain sentences describing the product and what it is for,
"category_id": the id of the best matching category from the list, or null if none fits.
Only describe what is given or clearly visible. Never invent sizes, materials, brands, prices, certifications or claims.
TXT;

        $prompt = "Categories (id: name):\n".($categoryList ?: '(none)')."\n\n"
            .'Keywords from the shop owner: '.(trim((string) $keywords) ?: '(none, use the photo)');

        $reply = $this->llm->complete($system, $prompt, $image ? $this->toDataUrl($image) : null);

        return $this->parse($reply, $categories->pluck('id')->all());
    }

    private function toDataUrl(UploadedFile $image): string
    {
        $mime = $image->getMimeType() ?: 'image/jpeg';

        return "data:{$mime};base64,".base64_encode($image->get());
    }

    /**
     * Free models sometimes wrap the JSON in prose or code fences, so find
     * the object, and never trust the values: check each one.
     *
     * @param  array<int, int>  $validCategoryIds
     * @return array{name: string, description: string, category_id: int|null}
     */
    private function parse(string $reply, array $validCategoryIds): array
    {
        $start = strpos($reply, '{');
        $end = strrpos($reply, '}');
        $json = ($start !== false && $end !== false && $end > $start)
            ? json_decode(substr($reply, $start, $end - $start + 1), true)
            : null;

        $name = is_array($json) ? trim((string) ($json['name'] ?? '')) : '';
        $description = is_array($json) ? trim((string) ($json['description'] ?? '')) : '';

        if ($name === '' || $description === '') {
            throw new LlmException('The AI answer could not be read. Try again.');
        }

        $categoryId = isset($json['category_id']) && is_numeric($json['category_id'])
            ? (int) $json['category_id']
            : null;

        return [
            'name' => mb_substr($name, 0, 255),
            'description' => $description,
            'category_id' => in_array($categoryId, $validCategoryIds, true) ? $categoryId : null,
        ];
    }
}
