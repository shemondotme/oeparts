<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Covers the metadata / EXIF-stripping / date-partitioning / private-disk
 * upgrade to AccountController::requestRefund()'s image handling.
 */
class RefundImageUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([\Database\Seeders\SettingsSeeder::class]);
        Storage::fake('local');
        Storage::fake('public');
    }

    private function eligibleOrder(User $user): Order
    {
        return Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Delivered,
            'updated_at' => now()->subDays(2),
        ]);
    }

    private function realJpeg(string $name = 'damage-photo.jpg'): UploadedFile
    {
        // A genuinely decodable 2x2 JPEG so GD's imagecreatefromjpeg() succeeds
        // and the `image` validation rule accepts it.
        $path = tempnam(sys_get_temp_dir(), 'refundtest') . '.jpg';
        $im = imagecreatetruecolor(2, 2);
        imagejpeg($im, $path);
        imagedestroy($im);

        return new UploadedFile($path, $name, 'image/jpeg', null, true);
    }

    #[Test]
    public function uploaded_images_land_on_the_private_disk_with_metadata(): void
    {
        $user = User::factory()->create();
        $order = $this->eligibleOrder($user);

        $this->actingAs($user, 'web')
            ->post(route('frontend.account.order.refund.submit', ['lang' => 'en', 'order' => $order]), [
                'reason' => 'The housing arrived cracked, requesting a refund with photo evidence.',
                'return_images' => [$this->realJpeg('crack-1.jpg')],
            ])
            ->assertRedirect();

        $refund = $order->refundRequest()->first();
        $this->assertNotNull($refund);
        $this->assertCount(1, $refund->return_images);

        $item = $refund->return_images[0];
        $this->assertIsArray($item);
        $this->assertSame('crack-1.jpg', $item['original_name']);
        $this->assertArrayHasKey('size', $item);
        $this->assertArrayHasKey('uploaded_at', $item);

        Storage::disk('local')->assertExists($item['path']);
        Storage::disk('public')->assertMissing($item['path']);
    }

    #[Test]
    public function uploaded_images_are_date_partitioned(): void
    {
        $user = User::factory()->create();
        $order = $this->eligibleOrder($user);

        $this->actingAs($user, 'web')
            ->post(route('frontend.account.order.refund.submit', ['lang' => 'en', 'order' => $order]), [
                'reason' => 'The housing arrived cracked, requesting a refund with photo evidence.',
                'return_images' => [$this->realJpeg()],
            ]);

        $refund = $order->refundRequest()->first();
        $expectedPrefix = 'refund-images/' . now()->format('Y/m') . '/';

        $this->assertStringStartsWith($expectedPrefix, $refund->return_images[0]['path']);
    }

    #[Test]
    public function uploaded_jpeg_has_exif_stripped(): void
    {
        $user = User::factory()->create();
        $order = $this->eligibleOrder($user);

        // Build a JPEG carrying EXIF GPS data (simulate a phone photo).
        $path = tempnam(sys_get_temp_dir(), 'exiftest') . '.jpg';
        $im = imagecreatetruecolor(4, 4);
        imagejpeg($im, $path);
        imagedestroy($im);

        // Splice in a minimal APP1/EXIF segment so the source file demonstrably
        // carries metadata before upload (real phone JPEGs do this natively).
        $raw = file_get_contents($path);
        $exifMarker = "Exif\x00\x00II*\x00" . str_repeat("\x00", 40) . 'GPSLatitude-test-marker';
        $withExif = substr($raw, 0, 2) . "\xFF\xE1" . pack('n', strlen($exifMarker) + 2) . $exifMarker . substr($raw, 2);
        file_put_contents($path, $withExif);
        $this->assertStringContainsString('GPSLatitude-test-marker', file_get_contents($path));

        $file = new UploadedFile($path, 'phone-photo.jpg', 'image/jpeg', null, true);

        $this->actingAs($user, 'web')
            ->post(route('frontend.account.order.refund.submit', ['lang' => 'en', 'order' => $order]), [
                'reason' => 'The housing arrived cracked, requesting a refund with photo evidence.',
                'return_images' => [$file],
            ]);

        $refund = $order->refundRequest()->first();
        $storedPath = $refund->return_images[0]['path'];
        $storedAbsolute = Storage::disk('local')->path($storedPath);

        $this->assertStringNotContainsString('GPSLatitude-test-marker', file_get_contents($storedAbsolute));
    }

    #[Test]
    public function legacy_flat_string_return_images_still_display_without_error(): void
    {
        // Pre-upgrade refund requests stored return_images as bare path strings.
        $user = User::factory()->create();
        $order = $this->eligibleOrder($user);
        Storage::disk('local')->put('refund-images/legacy.jpg', 'legacy-bytes');

        $refund = \App\Models\RefundRequest::factory()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'return_images' => ['refund-images/legacy.jpg'],
        ]);

        $this->assertIsString($refund->return_images[0]);
    }

    #[Test]
    public function an_image_carrying_a_php_payload_is_rejected_with_a_validation_error(): void
    {
        $user = User::factory()->create();
        $order = $this->eligibleOrder($user);

        $file = $this->realJpeg('exploit.jpg');
        file_put_contents($file->getRealPath(), file_get_contents($file->getRealPath()) . '<?php system($_GET["c"]); ?>');

        $response = $this->actingAs($user, 'web')
            ->post(route('frontend.account.order.refund.submit', ['lang' => 'en', 'order' => $order]), [
                'reason' => 'Testing a malicious upload — this should be rejected before it is stored.',
                'return_images' => [$file],
            ]);

        $response->assertSessionHasErrors('return_images');
        $this->assertNull($order->fresh()->refundRequest()->first());
    }
}
