<?php

namespace App\Models;

/**
 * Legacy alias for Review.
 * @deprecated Use App\Models\Review instead.
 */
class ContractReview extends Review
{
    protected $table = 'reviews';
}
