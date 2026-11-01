<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ProductMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductMedia
 */
class ProductMediaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'variant_id' => $this->variant_id,
            'alt_text' => $this->alt_text,
            'display_order' => $this->display_order,
            'mime_type' => $this->asset->mime_type,
            'byte_size' => $this->asset->byte_size,
            'sha256' => $this->asset->sha256,
            'classification' => $this->asset->classification->value,
            'scan_status' => $this->asset->scan_status->value,
            'download_url' => route('api.v1.products.media.download', [$this->product_id, $this->id]),
        ];
    }
}
