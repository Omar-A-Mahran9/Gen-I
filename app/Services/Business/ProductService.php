<?php

namespace App\Services\Business;

use App\Enums\ActiveStatus;
use App\Enums\MediaClassification;
use App\Enums\ProductStatus;
use App\Enums\ScanStatus;
use App\Enums\VariantStatus;
use App\Exceptions\AdminActionException;
use App\Models\AttributeDefinition;
use App\Models\MediaAsset;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\VariantAttribute;
use App\Rules\TypedAttributeValue;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Products, exact variants, typed variant specifications and product media (S2 §9.3, UC19, BR02).
 */
class ProductService
{
    use ScopedQuery;

    /**
     * Controlled publication lifecycle (UC19: approve before publish).
     */
    public const PRODUCT_TRANSITIONS = [
        'draft' => ['approved'],
        'approved' => ['published', 'draft'],
        'published' => ['retired'],
        'retired' => ['draft'],
    ];

    public const VARIANT_TRANSITIONS = [
        'active' => ['unavailable', 'retired'],
        'unavailable' => ['active', 'retired'],
        'retired' => [],
    ];

    public function __construct(protected AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Product>
     */
    public function query(User $user, array $filters = []): Builder
    {
        $query = Product::query()->forUser($user)
            ->with(['category:id,name_en,name_ar', 'brand:id,name', 'organization:id,name'])
            ->withCount('variants');

        $term = trim((string) ($filters['search'] ?? ''));
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(fn (Builder $q) => $q
                ->where('name_en', 'like', $like)->orWhere('name_ar', 'like', $like)->orWhere('model_code', 'like', $like)
                ->orWhereHas('variants', fn (Builder $v) => $v->where('sku', 'like', $like)));
        }

        $this->applyEquals($query, $filters, ['status', 'organization_id', 'category_id', 'brand_id']);

        if (filled($filters['item_kind'] ?? null)) {
            $query->whereHas('variants', fn (Builder $v) => $v->where('item_kind', $filters['item_kind']));
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): Product
    {
        return DB::transaction(function () use ($data, $actor): Product {
            $product = new Product($data);
            $product->status = ProductStatus::Draft;
            $product->save();

            $this->audit->log('product.created', $product, ['model_code' => $product->model_code], $actor);

            return $product;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Product $product, array $data, User $actor): Product
    {
        return DB::transaction(function () use ($product, $data, $actor): Product {
            if (isset($data['category_id']) && (int) $data['category_id'] !== $product->category_id && $product->variants()->whereHas('attributeValues')->exists()) {
                throw new AdminActionException(__('admin.errors.category_locked_by_specifications'));
            }

            $product->fill($data);
            $changed = array_keys($product->getDirty());
            $product->save();

            if ($changed !== []) {
                $this->audit->log('product.updated', $product, ['changed' => $changed], $actor);
            }

            return $product;
        });
    }

    public function transition(Product $product, ProductStatus $status, User $actor): Product
    {
        $from = $product->status;

        if (! in_array($status->value, self::PRODUCT_TRANSITIONS[$from->value] ?? [], true)) {
            throw new AdminActionException(__('admin.errors.invalid_transition', ['from' => $from->label(), 'to' => $status->label()]));
        }

        if ($status === ProductStatus::Published) {
            $product->loadMissing(['category', 'brand']);

            if ($product->category->status !== ActiveStatus::Active || $product->brand->status !== ActiveStatus::Active) {
                throw new AdminActionException(__('admin.errors.publish_requires_active_category_brand'));
            }

            if (! $product->variants()->where('status', VariantStatus::Active->value)->exists()) {
                throw new AdminActionException(__('admin.errors.publish_requires_variant'));
            }
        }

        $product->forceFill(['status' => $status])->save();
        $this->audit->log('product.status_changed', $product, ['from' => $from->value, 'to' => $status->value], $actor);

        return $product;
    }

    public function delete(Product $product, User $actor): void
    {
        if ($product->status !== ProductStatus::Draft) {
            throw new AdminActionException(__('admin.errors.only_draft_products_deletable'));
        }

        foreach ($product->variants as $variant) {
            $this->assertVariantUnused($variant);
        }

        DB::transaction(function () use ($product, $actor): void {
            $this->audit->log('product.deleted', $product, ['model_code' => $product->model_code], $actor);

            foreach ($product->media()->with('asset')->get() as $placement) {
                $this->removeMedia($placement, $actor, audit: false);
            }

            $product->variants()->each(fn (ProductVariant $variant) => $variant->delete());
            $product->delete();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveVariant(Product $product, array $data, User $actor, ?ProductVariant $variant = null): ProductVariant
    {
        $variant ??= new ProductVariant;
        $isNew = ! $variant->exists;

        $variant->fill($data);
        $variant->organization_id = $product->organization_id;
        $variant->product_id = $product->id;
        if ($isNew) {
            $variant->status = VariantStatus::Active;
        }
        $changed = array_keys($variant->getDirty());
        $variant->save();

        $this->audit->log($isNew ? 'variant.created' : 'variant.updated', $variant, $isNew ? ['sku' => $variant->sku] : ['sku' => $variant->sku, 'changed' => $changed], $actor);

        return $variant;
    }

    public function setVariantStatus(ProductVariant $variant, VariantStatus $status, User $actor): ProductVariant
    {
        $from = $variant->status;

        if ($from === $status) {
            return $variant;
        }

        if (! in_array($status->value, self::VARIANT_TRANSITIONS[$from->value] ?? [], true)) {
            throw new AdminActionException(__('admin.errors.invalid_transition', ['from' => $from->label(), 'to' => $status->label()]));
        }

        $variant->forceFill(['status' => $status])->save();
        $this->audit->log('variant.status_changed', $variant, ['sku' => $variant->sku, 'from' => $from->value, 'to' => $status->value], $actor);

        return $variant;
    }

    public function deleteVariant(ProductVariant $variant, User $actor): void
    {
        $this->assertVariantUnused($variant);

        $this->audit->log('variant.deleted', $variant, ['sku' => $variant->sku], $actor);
        $variant->delete();
    }

    protected function assertVariantUnused(ProductVariant $variant): void
    {
        if ($variant->prices()->exists() || $variant->inventoryBalances()->exists() || $variant->inventoryMovements()->exists()) {
            throw new AdminActionException(__('admin.errors.variant_in_use'));
        }
    }

    /**
     * Attribute definitions applicable to a product: its category and the category's ancestors.
     *
     * @return Collection<int, AttributeDefinition>
     */
    public function applicableAttributes(Product $product): Collection
    {
        return AttributeDefinition::query()
            ->whereIn('category_id', $product->category->lineageIds())
            ->orderBy('comparison_order')
            ->orderBy('code')
            ->get();
    }

    /**
     * Replace a variant's typed specifications. Exactly one typed value per attribute (S2).
     *
     * @param  array<int, array{attribute_id: int, value: mixed, source_reference: string, verified_at?: string|null}>  $values
     */
    public function syncVariantAttributes(ProductVariant $variant, array $values, User $actor): void
    {
        $variant->loadMissing('product.category');
        $definitions = $this->applicableAttributes($variant->product)->keyBy('id');

        DB::transaction(function () use ($variant, $values, $actor, $definitions): void {
            $keep = [];

            foreach ($values as $row) {
                $definition = $definitions->get((int) $row['attribute_id']);

                if ($definition === null) {
                    throw new AdminActionException(__('admin.errors.attribute_not_applicable'));
                }

                if ($row['value'] === null || $row['value'] === '') {
                    continue;
                }

                $typed = TypedAttributeValue::toColumns($definition->value_type, $row['value']);

                VariantAttribute::query()->updateOrCreate(
                    ['variant_id' => $variant->id, 'attribute_id' => $definition->id],
                    $typed + [
                        'organization_id' => $variant->organization_id,
                        'source_reference' => $row['source_reference'],
                        'verified_at' => $row['verified_at'] ?? now(),
                    ],
                );
                $keep[] = $definition->id;
            }

            $variant->attributeValues()->whereNotIn('attribute_id', $keep)->delete();

            $this->audit->log('variant.specifications_updated', $variant, ['sku' => $variant->sku, 'attributes' => count($keep)], $actor);
        });
    }

    /**
     * Merge typed specifications into a variant without removing values that are not supplied
     * (used by the catalog import). A value whose typed value and source are unchanged is left
     * untouched, including its verified_at, unless a new verified_at is supplied.
     *
     * @param  array<int, array{definition: AttributeDefinition, value: mixed, source_reference: string, verified_at?: string|null}>  $values
     * @return int number of specification rows created or changed
     */
    public function mergeVariantAttributes(ProductVariant $variant, array $values, User $actor): int
    {
        return DB::transaction(function () use ($variant, $values, $actor): int {
            $existing = $variant->attributeValues()->get()->keyBy('attribute_id');
            $written = [];

            foreach ($values as $row) {
                $definition = $row['definition'];

                if ((int) $definition->organization_id !== (int) $variant->organization_id) {
                    throw new AdminActionException(__('admin.errors.attribute_not_applicable'));
                }

                $typed = TypedAttributeValue::toColumns($definition->value_type, $row['value']);
                $verifiedAt = $row['verified_at'] ?? null;
                $current = $existing->get($definition->id);

                if ($current !== null && $verifiedAt === null && $current->source_reference === $row['source_reference'] && $this->sameTypedValue($current, $typed)) {
                    continue;
                }

                VariantAttribute::query()->updateOrCreate(
                    ['variant_id' => $variant->id, 'attribute_id' => $definition->id],
                    $typed + [
                        'organization_id' => $variant->organization_id,
                        'source_reference' => $row['source_reference'],
                        'verified_at' => $verifiedAt ?? now(),
                    ],
                );
                $written[] = $definition->code;
            }

            if ($written !== []) {
                $this->audit->log('variant.specifications_updated', $variant, ['sku' => $variant->sku, 'attributes' => count($written), 'codes' => $written], $actor);
            }

            return count($written);
        });
    }

    /**
     * @param  array{text_value: string|null, number_value: string|null, boolean_value: bool|null}  $typed
     */
    protected function sameTypedValue(VariantAttribute $current, array $typed): bool
    {
        return match (true) {
            $typed['number_value'] !== null => $current->number_value !== null && $this->canonicalDecimal((string) $current->number_value) === $this->canonicalDecimal($typed['number_value']),
            $typed['boolean_value'] !== null => $current->boolean_value === $typed['boolean_value'],
            default => $current->text_value === $typed['text_value'],
        };
    }

    /**
     * Canonical decimal(18,6) string ("6.1", "06.100000" and "6.1e0" all become "6.100000") without float rounding.
     */
    protected function canonicalDecimal(string $number): string
    {
        $number = trim($number);

        if (stripos($number, 'e') !== false) {
            $number = sprintf('%.6F', (float) $number);
        }

        $negative = str_starts_with($number, '-');
        [$integer, $fraction] = array_pad(explode('.', ltrim($number, '+-'), 2), 2, '');
        $integer = ltrim($integer, '0') ?: '0';
        $fraction = str_pad(substr($fraction, 0, 6), 6, '0');

        return ($negative && ($integer !== '0' || $fraction !== '000000') ? '-' : '').$integer.'.'.$fraction;
    }

    /**
     * Store an uploaded product image on the private disk. New assets are
     * "pending" until reviewed; only public + clean assets are ever exposed publicly.
     *
     * @param  array{alt_text: string, variant_id?: int|null, display_order?: int|null, rights_reference?: string|null}  $data
     */
    public function addMedia(Product $product, UploadedFile $file, array $data, User $actor): ProductMedia
    {
        $hash = hash_file('sha256', $file->getRealPath());
        $extension = $file->guessExtension() ?: 'bin';
        $key = 'media/'.$product->organization_id.'/'.$hash.'.'.$extension;

        return DB::transaction(function () use ($product, $file, $data, $actor, $hash, $key): ProductMedia {
            if (! Storage::disk(MediaAsset::DISK)->exists($key)) {
                Storage::disk(MediaAsset::DISK)->putFileAs(dirname($key), $file, basename($key));
            }

            $asset = MediaAsset::query()->create([
                'organization_id' => $product->organization_id,
                'object_key' => $key,
                'mime_type' => $file->getMimeType(),
                'sha256' => $hash,
                'byte_size' => $file->getSize(),
                'classification' => MediaClassification::Public->value,
                'scan_status' => ScanStatus::Pending->value,
                'rights_reference' => $data['rights_reference'] ?? null,
            ]);

            $placement = ProductMedia::query()->create([
                'organization_id' => $product->organization_id,
                'product_id' => $product->id,
                'variant_id' => $data['variant_id'] ?? null,
                'asset_id' => $asset->id,
                'alt_text' => $data['alt_text'],
                'display_order' => $data['display_order'] ?? ((int) $product->media()->max('display_order') + 1),
            ]);

            $this->audit->log('product.media_added', $product, ['asset_id' => $asset->id, 'sha256' => $hash, 'bytes' => $asset->byte_size], $actor);

            return $placement;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateMedia(ProductMedia $placement, array $data, User $actor): ProductMedia
    {
        DB::transaction(function () use ($placement, $data, $actor): void {
            $placement->fill(collect($data)->only(['alt_text', 'display_order', 'variant_id'])->all())->save();

            $asset = $placement->asset;
            if (isset($data['scan_status']) || isset($data['classification'])) {
                $from = ['scan_status' => $asset->scan_status->value, 'classification' => $asset->classification->value];
                $asset->forceFill(collect($data)->only(['scan_status', 'classification'])->all())->save();
                $this->audit->log('product.media_reviewed', $placement->product, ['asset_id' => $asset->id, 'from' => $from, 'to' => ['scan_status' => $asset->scan_status->value, 'classification' => $asset->classification->value]], $actor);
            }
        });

        return $placement->refresh();
    }

    public function removeMedia(ProductMedia $placement, User $actor, bool $audit = true): void
    {
        $asset = $placement->asset;

        if ($audit) {
            $this->audit->log('product.media_removed', $placement->product, ['asset_id' => $asset->id], $actor);
        }

        $placement->delete();

        if (! ProductMedia::query()->where('asset_id', $asset->id)->exists()) {
            $sharedFile = MediaAsset::query()->where('object_key', $asset->object_key)->whereKeyNot($asset->id)->exists();
            $asset->delete();

            if (! $sharedFile) {
                Storage::disk(MediaAsset::DISK)->delete($asset->object_key);
            }
        }
    }
}
