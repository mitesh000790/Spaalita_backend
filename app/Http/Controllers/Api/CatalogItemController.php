<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreCatalogItemRequest;
use App\Http\Resources\CatalogItemDetailResource;
use App\Http\Resources\CatalogItemListResource;
use App\Http\Resources\CatalogItemResource;
use App\Models\CatalogItem;
use App\Models\Category;
use App\Support\Js;
use App\Support\Like;
use App\Support\ListInput;
use App\Support\Pagination;
use App\Support\TypeCaster;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Endpoints shared by products and gift cards, which have the same fields
 * and behaviour and differ only in their messages.
 */
abstract class CatalogItemController extends Controller
{
    private const RELATED_LIMIT = 4;

    /**
     * @return class-string<CatalogItem>
     */
    abstract protected function model(): string;

    /**
     * Response messages, keyed by: created, createFailed, notFound, updated,
     * updateFailed, listFailed, showFailed, deleteNotFound, deleted,
     * deleteFailed, relatedNotFound, relatedFailed.
     *
     * @return array<string, string>
     */
    abstract protected function messages(): array;

    /**
     * GET / - active items that have at least one image.
     *
     * Query: page, limit, search (name, SKU or root-category name),
     * sort (latest | price_low_high | price_high_low | popular | recommended).
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $pagination = Pagination::strict($request);
            $search = $request->query('search', '');
            $lookup = Category::catalogLookup();

            $query = $this->query()
                ->notInactive()
                ->whereNotNull('product_images')
                ->whereJsonLength('product_images', '>', 0);

            if (Js::truthy($search)) {
                $pattern = Like::contains((string) $search);

                $query->where(fn (Builder $query) => $query
                    ->where('product_name', 'like', $pattern)
                    ->orWhere('sku', 'like', $pattern)
                    ->orWhereHas('categories', fn (Builder $categories) => $categories
                        ->whereNull('categories.parent_id')
                        ->where('categories.name', 'like', $pattern)));
            }

            $total = $query->count();

            $items = $pagination->apply($this->applySort($query, (string) $request->query('sort', '')))
                ->with('categories')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $items->map(fn (CatalogItem $item) => new CatalogItemListResource($item, $lookup)),
                'pagination' => [
                    'total' => $total,
                    'page' => $pagination->page,
                    'limit' => $pagination->limit,
                    'pages' => $pagination->pages($total),
                ],
            ]);
        } catch (Throwable $exception) {
            return $this->serverError($this->messages()['listFailed'], $exception);
        }
    }

    /**
     * POST / (multipart: up to 10 "productImages")
     */
    public function store(StoreCatalogItemRequest $request): JsonResponse
    {
        $messages = $this->messages();

        try {
            $sku = TypeCaster::string($request->input('sku'), 'sku', ['trim' => true]);

            if ($this->query()->where('sku', $sku)->exists()) {
                return response()->json(['success' => false, 'message' => 'SKU already exists'], 409);
            }

            $categoryIds = TypeCaster::objectIdArray($request->input('categories'), 'categories');

            $item = DB::transaction(function () use ($request, $sku, $categoryIds) {
                $model = $this->model();
                $item = new $model([
                    ...$this->attributes($request),
                    'product_name' => TypeCaster::string($request->input('productName'), 'productName', ['trim' => true]),
                    'sku' => $sku,
                    'price' => TypeCaster::number($request->input('price'), 'price'),
                    'qty' => TypeCaster::number($request->input('qty'), 'qty'),
                    'description' => TypeCaster::string($request->input('description'), 'description'),
                    'product_images' => $request->attributes->get('uploaded_files') ?? [],
                ]);
                $item->save();
                $item->syncCategoriesInOrder($categoryIds);

                return $item;
            });

            return response()->json([
                'success' => true,
                'message' => $messages['created'],
                'data' => new CatalogItemResource($item->load('categories')),
            ], 201);
        } catch (Throwable $exception) {
            return $this->serverError($messages['createFailed'], $exception);
        }
    }

    /**
     * GET /{id} - item details with expanded categories.
     */
    public function show(string $id): JsonResponse
    {
        $messages = $this->messages();

        try {
            $item = $this->query()->with('categories')->find($id);

            if ($item === null) {
                return response()->json(['success' => false, 'message' => $messages['notFound']], 404);
            }

            $lookup = Category::catalogLookup();
            $missing = $item->categories->pluck('id')->reject(fn (string $id) => isset($lookup[$id]));

            if ($missing->isNotEmpty()) {
                Log::warning("Product \"{$item->product_name}\" has missing categories:", $missing->all());
            }

            return response()->json(['success' => true, 'data' => new CatalogItemDetailResource($item, $lookup)]);
        } catch (Throwable $exception) {
            return $this->serverError($messages['showFailed'], $exception);
        }
    }

    /**
     * PUT /{id} (multipart: up to 10 new "productImages")
     *
     * The image list becomes `existingImages` followed by the new uploads.
     * Optional fields that are not sent are reset to their defaults.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $messages = $this->messages();

        try {
            $attributes = $this->attributes($request);

            $required = [
                'productName' => ['product_name', fn ($value) => TypeCaster::string($value, 'productName', ['trim' => true])],
                'sku' => ['sku', fn ($value) => TypeCaster::string($value, 'sku', ['trim' => true])],
                'price' => ['price', fn ($value) => TypeCaster::number($value, 'price')],
                'qty' => ['qty', fn ($value) => TypeCaster::number($value, 'qty')],
                'description' => ['description', fn ($value) => TypeCaster::string($value, 'description')],
            ];

            foreach ($required as $field => [$column, $cast]) {
                if ($request->exists($field)) {
                    $attributes[$column] = $cast($request->input($field));
                }
            }

            $existingImages = TypeCaster::stringArray(
                ListInput::parseOptional($request->input('existingImages')),
                'productImages',
            );
            $attributes['product_images'] = [...$existingImages, ...($request->attributes->get('uploaded_files') ?? [])];

            $categoryIds = TypeCaster::objectIdArray(
                StoreCatalogItemRequest::categoriesFrom($request->post()),
                'categories',
            );

            $item = $this->query()->find($id);

            if ($item === null) {
                return response()->json(['success' => false, 'message' => $messages['notFound'], 'data' => null], 404);
            }

            DB::transaction(function () use ($item, $attributes, $categoryIds) {
                $item->applyUpdate($attributes);
                $item->syncCategoriesInOrder($categoryIds);
            });

            return response()->json([
                'success' => true,
                'message' => $messages['updated'],
                'data' => new CatalogItemResource($item->load('categories')),
            ]);
        } catch (Throwable $exception) {
            return $this->serverError($messages['updateFailed'], $exception);
        }
    }

    /**
     * DELETE /{id} (soft delete: status becomes "inactive")
     */
    public function destroy(string $id): JsonResponse
    {
        $messages = $this->messages();

        try {
            $item = $this->query()->find($id);

            if ($item === null) {
                return response()->json(['success' => false, 'message' => $messages['deleteNotFound']], 404);
            }

            $item->applyUpdate(['status' => 'inactive']);

            return response()->json(['success' => true, 'message' => $messages['deleted']]);
        } catch (Throwable $exception) {
            return $this->serverError($messages['deleteFailed'], $exception);
        }
    }

    /**
     * GET /{id}/related - up to four other active items.
     */
    public function related(string $id): JsonResponse
    {
        $messages = $this->messages();

        try {
            $item = $this->query()->find($id);

            if ($item === null || $item->status === 'inactive') {
                return response()->json(['success' => false, 'message' => $messages['relatedNotFound']], 404);
            }

            $related = $this->query()
                ->with('categories')
                ->whereKeyNot($item->getKey())
                ->notInactive()
                ->orderBy('created_at')
                ->orderBy('id')
                ->limit(self::RELATED_LIMIT)
                ->get();

            return response()->json(['success' => true, 'data' => CatalogItemResource::collection($related)]);
        } catch (Throwable $exception) {
            return $this->serverError($messages['relatedFailed'], $exception);
        }
    }

    /**
     * @return Builder<CatalogItem>
     */
    protected function query(): Builder
    {
        return $this->model()::query();
    }

    /**
     * Optional fields, falling back to their defaults when missing or empty.
     *
     * @return array<string, mixed>
     */
    private function attributes(Request $request): array
    {
        $orDefault = fn (string $field, mixed $default) => Js::truthy($request->input($field)) ? $request->input($field) : $default;
        $ifPresent = fn (string $field, mixed $default) => $request->exists($field) ? $request->input($field) : $default;

        return [
            'slug' => TypeCaster::string($orDefault('slug', ''), 'slug'),
            'regular_price' => TypeCaster::string($orDefault('regular_price', ''), 'regular_price'),
            'sale_price' => TypeCaster::string($orDefault('sale_price', ''), 'sale_price'),
            'short_description' => TypeCaster::string($orDefault('short_description', ''), 'short_description'),
            'tax_status' => TypeCaster::string($orDefault('tax_status', 'none'), 'tax_status'),
            'shipping_required' => TypeCaster::boolean($ifPresent('shipping_required', true), 'shipping_required'),
            'shipping_taxable' => TypeCaster::boolean($ifPresent('shipping_taxable', false), 'shipping_taxable'),
            'stock_status' => TypeCaster::string($orDefault('stock_status', 'instock'), 'stock_status'),
            'related_ids' => TypeCaster::numberArray($orDefault('related_ids', []), 'related_ids'),
        ];
    }

    private function applySort(Builder $query, string $sort): Builder
    {
        return match ($sort) {
            'price_low_high' => $query->orderBy('price')->orderBy('id'),
            'price_high_low' => $query->orderByDesc('price')->orderBy('id'),
            // These sort keys never existed on the records, so MongoDB
            // returned items in insertion order.
            'popular', 'recommended' => $query->orderBy('created_at')->orderBy('id'),
            default => $query->newestFirst(),
        };
    }
}
