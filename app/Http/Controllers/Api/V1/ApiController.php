<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\RespondsWithHttpStatus;

/**
 * Base class of every /api/v1 controller: all responses use the LMS-style
 * {success, data, message} structure through RespondsWithHttpStatus.
 */
abstract class ApiController
{
    use RespondsWithHttpStatus;
}
