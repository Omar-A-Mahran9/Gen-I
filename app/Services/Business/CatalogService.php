<?php

namespace App\Services\Business;

use App\Enums\ActiveStatus;
use App\Exceptions\AdminActionException;
use App\Models\AttributeDefinition;
use App\Models\Brand;
use App\Models\Category;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Categories (hierarchical, cycle-free), brands and the typed attribute dictionary (S2 §9.3).
 */
class CatalogService
{
    use ScopedQuery;

    public function __construct(protected AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Category>
     */
    public function categoryQuery(User $user, array $filters = []): Builder
    {
        $query = Category::query()->forUser($user)
            ->with(['parent:id,name_en,name_ar', 'organization:id,name'])
            ->withCount(['children', 'products', 'attributeDefinitions']);

        $this->applySearch($query, $filters['search'] ?? null, ['code', 'name_en', 'name_ar']);
        $this->applyEquals($query, $filters, ['status', 'organization_id', 'parent_id']);

        if (($filters['root'] ?? null) === '1') {
            $query->whereNull('parent_id');
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveCategory(array $data, User $actor, ?Category $category = null): Category
    {
        return DB::transaction(function () use ($data, $actor, $category): Category {
            $category ??= new Category;
            $isNew = ! $category->exists;

            if (! $isNew && ! empty($data['parent_id']) && $this->wouldCreateCycle($category, (int) $data['parent_id'])) {
                throw new AdminActionException(__('admin.errors.category_cycle'));
            }

            $category->fill($data);
            if ($isNew) {
                $category->status = ActiveStatus::Active;
            }
            $changed = array_keys($category->getDirty());
            $category->save();

            $this->audit->log($isNew ? 'category.created' : 'category.updated', $category, $isNew ? ['code' => $category->code] : ['changed' => $changed], $actor);

            return $category;
        });
    }

    public function wouldCreateCycle(Category $category, int $parentId): bool
    {
        return in_array($category->id, Category::query()->findOrFail($parentId)->lineageIds(), true);
    }

    public function setCategoryStatus(Category $category, ActiveStatus $status, User $actor): Category
    {
        return $this->setStatus($category, $status, $actor, 'category');
    }

    public function deleteCategory(Category $category, User $actor): void
    {
        if ($category->children()->exists() || $category->products()->exists() || $category->attributeDefinitions()->exists()) {
            throw new AdminActionException(__('admin.errors.has_dependents'));
        }

        $this->audit->log('category.deleted', $category, ['code' => $category->code], $actor);
        $category->delete();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Brand>
     */
    public function brandQuery(User $user, array $filters = []): Builder
    {
        $query = Brand::query()->forUser($user)->with('organization:id,name')->withCount('products');

        $this->applySearch($query, $filters['search'] ?? null, ['name']);
        $this->applyEquals($query, $filters, ['status', 'organization_id']);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveBrand(array $data, User $actor, ?Brand $brand = null): Brand
    {
        $brand ??= new Brand;
        $isNew = ! $brand->exists;
        $brand->fill($data);
        if ($isNew) {
            $brand->status = ActiveStatus::Active;
        }
        $brand->save();

        $this->audit->log($isNew ? 'brand.created' : 'brand.updated', $brand, ['name' => $brand->name], $actor);

        return $brand;
    }

    public function setBrandStatus(Brand $brand, ActiveStatus $status, User $actor): Brand
    {
        return $this->setStatus($brand, $status, $actor, 'brand');
    }

    public function deleteBrand(Brand $brand, User $actor): void
    {
        if ($brand->products()->exists()) {
            throw new AdminActionException(__('admin.errors.has_dependents'));
        }

        $this->audit->log('brand.deleted', $brand, ['name' => $brand->name], $actor);
        $brand->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveAttribute(Category $category, array $data, User $actor, ?AttributeDefinition $attribute = null): AttributeDefinition
    {
        $attribute ??= new AttributeDefinition;
        $isNew = ! $attribute->exists;

        // The value type cannot change once typed values exist (S2: type must match definition).
        if (! $isNew && isset($data['value_type']) && $attribute->value_type->value !== $data['value_type'] && $attribute->values()->exists()) {
            throw new AdminActionException(__('admin.errors.attribute_type_locked'));
        }

        $attribute->fill($data);
        $attribute->organization_id = $category->organization_id;
        $attribute->category_id = $category->id;
        $attribute->save();

        $this->audit->log($isNew ? 'attribute.created' : 'attribute.updated', $category, ['attribute' => $attribute->code], $actor);

        return $attribute;
    }

    public function deleteAttribute(AttributeDefinition $attribute, User $actor): void
    {
        if ($attribute->values()->exists()) {
            throw new AdminActionException(__('admin.errors.has_dependents'));
        }

        $this->audit->log('attribute.deleted', $attribute->category, ['attribute' => $attribute->code], $actor);
        $attribute->delete();
    }

    /**
     * @template T of Category|Brand
     *
     * @param  T  $model
     * @return T
     */
    protected function setStatus(Category|Brand $model, ActiveStatus $status, User $actor, string $event): Category|Brand
    {
        if ($model->status !== $status) {
            $from = $model->status;
            $model->forceFill(['status' => $status])->save();
            $this->audit->log("{$event}.status_changed", $model, ['from' => $from->value, 'to' => $status->value], $actor);
        }

        return $model;
    }
}
