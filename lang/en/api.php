<?php

return [
    'messages' => [
        'retrieved' => 'Data retrieved successfully.',
        'created' => 'Created successfully.',
        'updated' => 'Updated successfully.',
        'deleted' => 'Deleted successfully.',
        'status_changed' => 'Status changed successfully.',
        'approved' => 'Approved successfully.',
        'retired' => 'Retired successfully.',
        'stock_adjusted' => 'Stock updated successfully.',
        'idempotent_replay' => 'This request was already processed; the original result is returned.',
        'token_issued' => 'Token issued successfully.',
        'token_revoked' => 'Token revoked successfully.',
    ],
    'errors' => [
        'bad_request' => 'The request is malformed.',
        'validation_failed' => 'The given data was invalid.',
        'unauthenticated' => 'Authentication is required.',
        'forbidden' => 'You are not allowed to perform this action.',
        'not_found' => 'The requested resource was not found.',
        'method_not_allowed' => 'This HTTP method is not supported for this endpoint.',
        'conflict' => 'The request conflicts with the current state of the resource.',
        'payload_too_large' => 'The request is too large.',
        'unsupported_media_type' => 'The request content type is not supported.',
        'rate_limited' => 'Too many requests. Please slow down.',
        'service_unavailable' => 'The service is temporarily unavailable.',
        'server_error' => 'An unexpected error occurred.',
    ],
    'listing' => [
        'filter_format' => 'Filters must be sent as filter[field]=value.',
        'unknown_filter' => 'Unsupported filter: :keys. Allowed filters: :allowed.',
        'invalid_sort' => 'Unsupported sort field. Allowed fields: :allowed (prefix with "-" for descending order).',
        'invalid_per_page' => 'per_page must be a whole number between 1 and :max.',
        'invalid_page' => 'page must be a whole number of at least 1.',
    ],
];
