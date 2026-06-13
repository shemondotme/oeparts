<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\AbandonedCart;
use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\BlogPost;
use App\Models\CarModel;
use App\Models\Carrier;
use App\Models\Category;
use App\Models\ContactMessage;
use App\Models\Coupon;
use App\Models\CronLog;
use App\Models\EmailLog;
use App\Models\FailedSearchLog;
use App\Models\Faq;
use App\Models\IpBlocklist;
use App\Models\Language;
use App\Models\LoginLog;
use App\Models\Manufacturer;
use App\Models\MediaFile;
use App\Models\Menu;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\Page;
use App\Models\PartInquiry;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Redirect;
use App\Models\RefundRequest;
use App\Models\SearchLog;
use App\Models\Section;
use App\Models\SeoMeta;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Models\Testimonial;
use App\Models\Translation;
use App\Models\User;
use App\Policies\AbandonedCartPolicy;
use App\Policies\ActivityLogPolicy;
use App\Policies\AdminPolicy;
use App\Policies\BlogPostPolicy;
use App\Policies\CarModelPolicy;
use App\Policies\CarrierPolicy;
use App\Policies\CategoryPolicy;
use App\Policies\ContactMessagePolicy;
use App\Policies\CouponPolicy;
use App\Policies\CronLogPolicy;
use App\Policies\EmailLogPolicy;
use App\Policies\FailedSearchLogPolicy;
use App\Policies\FaqPolicy;
use App\Policies\IpBlocklistPolicy;
use App\Policies\LanguagePolicy;
use App\Policies\LoginLogPolicy;
use App\Policies\ManufacturerPolicy;
use App\Policies\MediaFilePolicy;
use App\Policies\MenuPolicy;
use App\Policies\NewsletterCampaignPolicy;
use App\Policies\NewsletterSubscriberPolicy;
use App\Policies\OrderPolicy;
use App\Policies\PagePolicy;
use App\Policies\PartInquiryPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\ProductPolicy;
use App\Policies\RedirectPolicy;
use App\Policies\RefundRequestPolicy;
use App\Policies\RolePolicy;
use App\Policies\SearchLogPolicy;
use App\Policies\SectionPolicy;
use App\Policies\SeoMetaPolicy;
use App\Policies\ShippingMethodPolicy;
use App\Policies\ShippingZonePolicy;
use App\Policies\TestimonialPolicy;
use App\Policies\TranslationPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Spatie\Permission\Models\Role;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        AbandonedCart::class => AbandonedCartPolicy::class,
        ActivityLog::class => ActivityLogPolicy::class,
        Admin::class => AdminPolicy::class,
        BlogPost::class => BlogPostPolicy::class,
        CarModel::class => CarModelPolicy::class,
        Carrier::class => CarrierPolicy::class,
        Category::class => CategoryPolicy::class,
        ContactMessage::class => ContactMessagePolicy::class,
        Coupon::class => CouponPolicy::class,
        CronLog::class => CronLogPolicy::class,
        EmailLog::class => EmailLogPolicy::class,
        FailedSearchLog::class => FailedSearchLogPolicy::class,
        Faq::class => FaqPolicy::class,
        IpBlocklist::class => IpBlocklistPolicy::class,
        Language::class => LanguagePolicy::class,
        LoginLog::class => LoginLogPolicy::class,
        Manufacturer::class => ManufacturerPolicy::class,
        MediaFile::class => MediaFilePolicy::class,
        Menu::class => MenuPolicy::class,
        NewsletterCampaign::class => NewsletterCampaignPolicy::class,
        NewsletterSubscriber::class => NewsletterSubscriberPolicy::class,
        Order::class => OrderPolicy::class,
        Page::class => PagePolicy::class,
        PartInquiry::class => PartInquiryPolicy::class,
        Payment::class => PaymentPolicy::class,
        Product::class => ProductPolicy::class,
        Redirect::class => RedirectPolicy::class,
        RefundRequest::class => RefundRequestPolicy::class,
        Role::class => RolePolicy::class,
        SearchLog::class => SearchLogPolicy::class,
        Section::class => SectionPolicy::class,
        SeoMeta::class => SeoMetaPolicy::class,
        ShippingMethod::class => ShippingMethodPolicy::class,
        ShippingZone::class => ShippingZonePolicy::class,
        Testimonial::class => TestimonialPolicy::class,
        Translation::class => TranslationPolicy::class,
        User::class => UserPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
