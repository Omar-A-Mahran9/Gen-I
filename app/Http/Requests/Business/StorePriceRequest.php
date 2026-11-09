<?php

namespace App\Http\Requests\Business;

/**
 * Create a Price child record. Authorization (update permission on the parent) and rules live in PriceRequest.
 */
class StorePriceRequest extends PriceRequest {}
