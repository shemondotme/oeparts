<?php

namespace App\Jobs;

use App\Models\ProductImage;
use App\Support\AdminNotifier;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Generates the thumbnail (150x150 cropped) and medium (800px longest edge)
 * derived images for a newly-uploaded ProductImage. Shaped exactly like
 * RegenerateSitemap: on QUEUE_CONNECTION=sync (shared hosting, no worker
 * required) this runs inline on save with the same latency trade-off
 * already accepted there.
 *
 * Graceful degradation is mandatory, not optional: if GD isn't available,
 * or this job fails/hasn't run yet, ProductImage's URL accessors fall back
 * to the original upload — the gallery never breaks, it just temporarily
 * serves full-size. handle() reflects that by no-op'ing cleanly rather than
 * throwing when GD is missing.
 */
class ProcessProductImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public array $backoff = [15];

    public int $timeout = 60;

    public function __construct(
        public int $productImageId,
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        if (! extension_loaded('gd')) {
            // Not an error — just means this environment serves full-size
            // images until GD is available. Nothing to notify about.
            return;
        }

        $image = ProductImage::find($this->productImageId);

        if (! $image) {
            // Row deleted before the job ran — nothing to process.
            return;
        }

        $disk = Storage::disk('public');
        $manager = ImageManager::gd();
        $source = $manager->read($disk->path($image->path));

        $thumbnailPath = $this->derivedPath($image->path, 'thumb');
        $mediumPath = $this->derivedPath($image->path, 'medium');

        // Image::save() doesn't exist in Intervention v3 — encode to an
        // EncodedImage first (that's what has save()), via the encoder
        // matching the original file's extension.
        $thumbnail = (clone $source)->cover(150, 150);
        $this->encodeFor($thumbnail, $image->path)->save($disk->path($thumbnailPath));

        $medium = (clone $source)->scaleDown(width: 800);
        $this->encodeFor($medium, $image->path)->save($disk->path($mediumPath));

        $image->update([
            'thumbnail_path' => $thumbnailPath,
            'medium_path' => $mediumPath,
        ]);
    }

    public function failed(Throwable $e): void
    {
        try {
            AdminNotifier::toRoles(
                ['super_admin', 'admin'],
                Notification::make()
                    ->title('Product image processing failed')
                    ->body("Image #{$this->productImageId}: {$e->getMessage()} — the gallery still serves the original upload.")
                    ->icon('heroicon-o-photo')
                    ->iconColor('danger')
            );
        } catch (Throwable $inner) {
            // A bell notification must never mask the original failure.
        }
    }

    private function derivedPath(string $originalPath, string $suffix): string
    {
        $directory = pathinfo($originalPath, PATHINFO_DIRNAME);
        $filename = pathinfo($originalPath, PATHINFO_FILENAME);
        $extension = pathinfo($originalPath, PATHINFO_EXTENSION);

        return "{$directory}/{$filename}-{$suffix}.{$extension}";
    }

    /**
     * Picks the encoder matching the original upload's extension —
     * Intervention v3 has no extension-sniffing save(), only format-
     * specific to*() methods that each return an EncodedImage.
     */
    private function encodeFor(\Intervention\Image\Interfaces\ImageInterface $image, string $path): \Intervention\Image\Interfaces\EncodedImageInterface
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => $image->toPng(),
            'webp' => $image->toWebp(),
            'gif' => $image->toGif(),
            default => $image->toJpeg(),
        };
    }
}
