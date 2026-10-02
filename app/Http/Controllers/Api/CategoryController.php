<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryNodeResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\CategorySearchResultResource;
use App\Models\Category;
use App\Support\Js;
use App\Support\Like;
use App\Support\ObjectId;
use App\Support\Pagination;
use App\Support\TypeCaster;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Category tree management.
 *
 * Only root categories and their direct children can be addressed by id,
 * and only root categories can be parents (the original data model
 * embedded sub-categories inside their root category).
 */
class CategoryController extends Controller
{
    private const UPLOAD_FOLDER = 'categories';

    private const NO_PARENT = 'none';

    /**
     * GET /api/category?page=&limit=&search=
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $search = (string) $request->query('search', '');

            return $search === ''
                ? $this->listRoots($request)
                : $this->searchRoots($request, $search);
        } catch (Throwable $exception) {
            return $this->serverError('Error fetching categories', $exception);
        }
    }

    /**
     * POST /api/category (multipart: optional "image")
     *
     * With a `parentId` (other than "none") the category is added as a
     * sub-category of that root category.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $parentId = $request->input('parentId');
            $image = $this->imageFrom($request);

            if (Js::truthy($parentId) && $parentId !== self::NO_PARENT) {
                $parent = $this->findRoot($parentId);

                if ($parent === null) {
                    return response()->json(['success' => false, 'message' => 'Parent category not found'], 404);
                }

                $child = DB::transaction(function () use ($request, $parent, $image) {
                    $child = Category::query()->create([
                        'parent_id' => $parent->id,
                        'position' => Category::nextPosition($parent->id),
                        'name' => TypeCaster::string($request->input('name'), 'name'),
                        'slug' => TypeCaster::string($request->input('slug'), 'slug'),
                        'description' => TypeCaster::string(Js::truthy($request->input('description')) ? $request->input('description') : '', 'description'),
                        'image_src' => $image,
                        'count' => TypeCaster::number(Js::truthy($request->input('count')) ? $request->input('count') : 0, 'count'),
                    ]);
                    $parent->touch();

                    return $child;
                });

                // The response echoes the submitted values (e.g. count "5" stays a string).
                return response()->json([
                    'success' => true,
                    'message' => 'Child category added successfully',
                    'data' => [
                        '_id' => $child->id,
                        'name' => $request->input('name'),
                        'slug' => $request->input('slug'),
                        'description' => Js::truthy($request->input('description')) ? $request->input('description') : '',
                        'image' => $image,
                        'count' => Js::truthy($request->input('count')) ? $request->input('count') : 0,
                        'children' => [],
                    ],
                ], 201);
            }

            $category = Category::query()->create([
                'name' => TypeCaster::string($request->input('name'), 'name'),
                'slug' => TypeCaster::string($request->input('slug'), 'slug'),
                'description' => TypeCaster::string($request->input('description', ''), 'description'),
                'image_src' => $image,
                'count' => TypeCaster::number($request->input('count'), 'count') ?? 0,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Category added successfully',
                'data' => new CategoryResource($category->setRelation('children', new Collection)),
            ], 201);
        } catch (Throwable $exception) {
            return $this->serverError('Error adding category', $exception);
        }
    }

    /**
     * GET /api/category/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            if (! ObjectId::isValid($id) && strlen($id) !== 12) {
                return response()->json(['success' => false, 'message' => 'Invalid ID'], 400);
            }

            $category = Category::query()->with('parent')->find($id);

            if ($category?->isRoot()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Category fetched successfully',
                    'data' => new CategoryResource(Category::withDescendants(new Collection([$category]))->first()),
                ]);
            }

            if ($category?->isFirstLevelChild()) {
                Category::withDescendants(new Collection([$category]));

                return response()->json([
                    'success' => true,
                    'message' => 'Child category fetched successfully',
                    'data' => (new CategoryNodeResource($category))->withParentId($category->parent_id),
                ]);
            }

            return response()->json(['success' => false, 'message' => 'Category not found'], 404);
        } catch (Throwable $exception) {
            return $this->serverError('Error fetching category', $exception);
        }
    }

    /**
     * PUT /api/category/{id} (multipart: optional "image")
     *
     * Updates the fields sent and optionally moves the category:
     * parentId "none" makes it a root category, a root category id makes it
     * a sub-category of that root (its own sub-categories move with it).
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $category = Category::query()->with('parent')->find($id);

            if ($category === null || ! $category->isAddressable()) {
                return response()->json(['success' => false, 'message' => 'Category not found'], 404);
            }

            $this->fillFromRequest($category, $request);
            $parentId = $request->input('parentId');
            $wantsMove = Js::truthy($parentId);

            return $category->isRoot()
                ? $this->updateRoot($category, $wantsMove ? $parentId : null)
                : $this->updateChild($category, $wantsMove ? $parentId : null);
        } catch (Throwable $exception) {
            return $this->serverError('Error updating category', $exception);
        }
    }

    /**
     * DELETE /api/category/{id} - also deletes its sub-categories.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $category = Category::query()->with('parent')->find($id);

            if ($category === null || ! $category->isAddressable()) {
                return response()->json(['success' => false, 'message' => 'Category not found'], 404);
            }

            $parent = $category->parent;
            $category->delete();
            $parent?->touch();

            return response()->json([
                'success' => true,
                'message' => $parent === null ? 'Category deleted successfully' : 'Child category deleted successfully',
            ]);
        } catch (Throwable $exception) {
            return $this->serverError('Error deleting category', $exception);
        }
    }

    private function listRoots(Request $request): JsonResponse
    {
        $pagination = Pagination::lenient($request);
        $query = Category::query()->whereNull('parent_id');

        $total = $query->count();
        $roots = Category::withDescendants(
            $pagination->apply($query->newestFirst())->get()
        );

        return $this->paginatedResponse(CategoryResource::collection($roots), $total, $pagination);
    }

    /**
     * Root categories whose own name or a sub-category name matches; only
     * the matching sub-categories are returned with each root.
     */
    private function searchRoots(Request $request, string $search): JsonResponse
    {
        $pagination = Pagination::strict($request);
        $pattern = Like::contains($search);

        $query = Category::query()
            ->whereNull('parent_id')
            ->where(fn (Builder $query) => $query
                ->where('name', 'like', $pattern)
                ->orWhereHas('children', fn (Builder $children) => $children->where('name', 'like', $pattern)));

        $total = $query->count();
        $roots = Category::withDescendants(
            $pagination->apply($query->orderBy('created_at')->orderBy('id'))->get()
        );

        $matchingChildIds = Category::query()
            ->whereIn('parent_id', $roots->modelKeys())
            ->where('name', 'like', $pattern)
            ->pluck('id')
            ->all();

        $results = $roots->map(fn (Category $root) => new CategorySearchResultResource(
            $root,
            $root->children->filter(fn (Category $child) => in_array($child->id, $matchingChildIds, true))->values(),
        ));

        return $this->paginatedResponse($results, $total, $pagination);
    }

    private function paginatedResponse(mixed $data, int $total, Pagination $pagination): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Categories fetched successfully',
            'data' => $data,
            'pagination' => [
                'total' => $total,
                'page' => $pagination->page,
                'limit' => $pagination->limit,
                'pages' => $pagination->pages($total),
            ],
        ]);
    }

    private function updateRoot(Category $category, mixed $parentId): JsonResponse
    {
        if ($parentId === null || $parentId === self::NO_PARENT) {
            $category->validateSchema();
            $category->save();

            return response()->json([
                'success' => true,
                'message' => $parentId === null ? 'Category updated successfully' : 'Category updated successfully as root',
                'data' => new CategoryResource(Category::withDescendants(new Collection([$category]))->first()),
            ]);
        }

        $newParent = $this->findRoot($parentId);

        if ($newParent === null) {
            return response()->json(['success' => false, 'message' => 'New parent not found'], 404);
        }

        if ($newParent->is($category)) {
            throw new RuntimeException('A category cannot be moved under itself');
        }

        $this->moveUnder($category, $newParent);

        $data = (new CategoryNodeResource(Category::withDescendants(new Collection([$category]))->first()))->resolve();
        // A former root without an image reports it as an empty object.
        $data['image'] ??= (object) [];

        return response()->json([
            'success' => true,
            'message' => 'Category moved under new parent successfully',
            'data' => $data,
        ]);
    }

    private function updateChild(Category $category, mixed $parentId): JsonResponse
    {
        $parent = $category->parent;

        if ($parentId === self::NO_PARENT) {
            $category->validateSchema();

            DB::transaction(function () use ($category, $parent) {
                $category->parent_id = null;
                $category->position = 0;
                $category->created_at = $category->freshTimestamp();
                $category->save();
                $parent->touch();
            });

            return response()->json([
                'success' => true,
                'message' => 'Child category moved to root successfully',
                'data' => new CategoryResource(Category::withDescendants(new Collection([$category->setRelation('parent', null)]))->first()),
            ]);
        }

        if ($parentId !== null && $parentId !== $parent->id) {
            $newParent = $this->findRoot($parentId);

            if ($newParent === null) {
                return response()->json(['success' => false, 'message' => 'New parent not found'], 404);
            }

            $this->moveUnder($category, $newParent);
            $parent->touch();

            return response()->json([
                'success' => true,
                'message' => 'Child category moved under new parent successfully',
                'data' => new CategoryNodeResource(Category::withDescendants(new Collection([$category]))->first()),
            ]);
        }

        $category->validateSchema();

        DB::transaction(function () use ($category, $parent) {
            $category->save();
            $parent->touch();
        });

        return response()->json([
            'success' => true,
            'message' => 'Child category updated successfully',
            'data' => new CategoryNodeResource(Category::withDescendants(new Collection([$category]))->first()),
        ]);
    }

    private function moveUnder(Category $category, Category $newParent): void
    {
        $category->validateSchema();

        DB::transaction(function () use ($category, $newParent) {
            $category->parent_id = $newParent->id;
            $category->position = Category::nextPosition($newParent->id);
            $category->save();
            $newParent->touch();
        });
    }

    /**
     * Apply the fields sent in the request; null or missing values keep the current value.
     */
    private function fillFromRequest(Category $category, Request $request): void
    {
        $image = $this->imageFrom($request);

        $category->name = TypeCaster::string($request->input('name') ?? $category->name, 'name');
        $category->slug = TypeCaster::string($request->input('slug') ?? $category->slug, 'slug');
        $category->description = TypeCaster::string($request->input('description') ?? $category->description, 'description');
        $category->count = TypeCaster::number($request->input('count') ?? $category->count, 'count') ?? 0;

        if ($image !== null) {
            $category->image_id = null;
            $category->image_src = $image;
        }
    }

    /**
     * Image path from an uploaded file, or from an `image` value ({src} or a URL string).
     */
    private function imageFrom(Request $request): ?string
    {
        $uploaded = $request->attributes->get('uploaded_file');

        if ($uploaded !== null) {
            return '/uploads/'.self::UPLOAD_FOLDER.'/'.$uploaded;
        }

        $image = $request->input('image');

        if (is_array($image)) {
            $image = $image['src'] ?? null;
        }

        return Js::truthy($image) ? TypeCaster::string($image, 'image') : null;
    }

    private function findRoot(mixed $id): ?Category
    {
        return is_string($id) ? Category::query()->whereNull('parent_id')->find($id) : null;
    }
}
