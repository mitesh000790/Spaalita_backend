<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Product category tree (adjacency list).
 *
 * In the original backend only root categories were MongoDB documents and
 * their sub-categories were embedded in a `children` array, so the API can
 * only address roots and their direct children. That rule is preserved by
 * isAddressable() and catalogLookup().
 */
class Category extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'o_id',
        'parent_id',
        'name',
        'slug',
        'description',
        'image_id',
        'image_src',
        'count',
        'position',
    ];

    protected $attributes = [
        'description' => '',
        'count' => 0,
        'position' => 0,
    ];

    protected function casts(): array
    {
        return [
            'o_id' => 'integer',
            'image_id' => 'integer',
            'count' => 'integer',
            'position' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'category_product');
    }

    public function giftCards(): BelongsToMany
    {
        return $this->belongsToMany(GiftCard::class, 'category_gift_card');
    }

    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * Whether the category is a direct child of a root category.
     */
    public function isFirstLevelChild(): bool
    {
        return $this->parent_id !== null && $this->parent?->parent_id === null;
    }

    /**
     * Roots and their direct children are the categories the API can address.
     */
    public function isAddressable(): bool
    {
        return $this->isRoot() || $this->isFirstLevelChild();
    }

    /**
     * Position for a category appended to the end of $parentId's children.
     */
    public static function nextPosition(string $parentId): int
    {
        return (int) static::query()->where('parent_id', $parentId)->max('position') + 1;
    }

    /**
     * Every root category (oldest first) with its full sub-category tree loaded.
     */
    public static function tree(): Collection
    {
        return static::withDescendants(
            static::query()->whereNull('parent_id')->orderBy('created_at')->orderBy('id')->get()
        );
    }

    /**
     * Attach the ordered `children` relation, recursively, to the given
     * categories using a single query for all sub-categories.
     *
     * @template TCollection of Collection<int, static>
     *
     * @param  TCollection  $categories
     * @return TCollection
     */
    public static function withDescendants(Collection $categories): Collection
    {
        $byParent = static::query()
            ->whereNotNull('parent_id')
            ->orderBy('position')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->groupBy('parent_id');

        $attach = function (Collection $nodes) use (&$attach, $byParent): void {
            foreach ($nodes as $node) {
                $children = new Collection($byParent->get($node->id, []));
                $node->setRelation('children', $children);
                $attach($children);
            }
        };

        $attach($categories);

        return $categories;
    }

    /**
     * Summaries ({_id, name, slug}) of every root category and its direct
     * children, keyed by id: the lookup the product endpoints use to expand
     * category ids.
     *
     * @return array<string, array{_id: string, name: string, slug: string}>
     */
    public static function catalogLookup(): array
    {
        $lookup = [];

        foreach (static::tree() as $root) {
            $lookup[$root->id] = $root->summary();

            foreach ($root->children as $child) {
                $lookup[$child->id] ??= $child->summary();
            }
        }

        return $lookup;
    }

    /**
     * @return array{_id: string, name: string, slug: string}
     */
    public function summary(): array
    {
        return ['_id' => $this->id, 'name' => $this->name, 'slug' => $this->slug];
    }

    protected function schemaRules(): array
    {
        return [
            'name' => ['path' => 'name', 'required' => true],
            'slug' => ['path' => 'slug', 'required' => true],
        ];
    }
}
