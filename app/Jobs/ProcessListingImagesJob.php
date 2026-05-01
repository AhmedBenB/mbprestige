<?php

namespace App\Jobs;

use App\Models\Listing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ProcessListingImagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public readonly int $listingId) {}

    public function handle(): void
    {
        $listing = Listing::with('images')->find($this->listingId);
        if (! $listing) {
            return;
        }

        foreach ($listing->images as $image) {
            if ($image->processing_status !== 'pending') {
                continue;
            }

            try {
                $image->update(['processing_status' => 'downloading']);

                $contents = @file_get_contents($image->source_url);
                if ($contents === false) {
                    throw new \RuntimeException("Download failed: {$image->source_url}");
                }

                $checksum = md5($contents);
                $path = "listings/{$listing->id}/{$checksum}.jpg";

                Storage::disk('public')->put($path, $contents);

                $tmpFile = tempnam(sys_get_temp_dir(), 'img');
                file_put_contents($tmpFile, $contents);
                [$width, $height] = @getimagesize($tmpFile) ?: [null, null];
                @unlink($tmpFile);

                $image->update([
                    'local_path' => $path,
                    'cdn_url' => Storage::disk('public')->url($path),
                    'checksum' => $checksum,
                    'width' => $width,
                    'height' => $height,
                    'processing_status' => 'ready',
                ]);
            } catch (\Throwable $e) {
                $image->update(['processing_status' => 'failed']);
            }
        }

        if ($listing->images()->where('processing_status', 'ready')->exists()) {
            if ($listing->publication_status->value === 'media_processing') {
                $listing->update(['publication_status' => 'ready_for_review']);
            }
        }
    }
}
