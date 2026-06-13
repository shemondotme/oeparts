<?php

namespace App\Filament\Resources\TestimonialResource\Pages;

use App\Filament\Concerns\DisablesCreateAnother;
use App\Filament\Resources\TestimonialResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTestimonial extends CreateRecord
{
    use DisablesCreateAnother;

    protected static string $resource = TestimonialResource::class;

    protected ?string $heading = 'New Testimonial';

    protected ?string $subheading = 'Add a customer testimonial to build trust and social proof on the storefront.';
}
