<?php

declare(strict_types=1);

namespace App\Policies;

class PartInquiryPolicy extends BasePolicy
{
    protected string $model = 'part_inquiries';
    protected ?string $permissionKey = 'inquiries';
}
