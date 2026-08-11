<?php

namespace App\Services;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class ProfilePhotoProcessor
{
    private const OUTPUT_SIZE = 512;

    /** @return array{content: string, mimeType: 'image/webp', sizeBytes: int, checksum: string} */
    public function process(UploadedFile $photo): array
    {
        $sourceContent = file_get_contents($photo->getRealPath());
        $source = is_string($sourceContent) ? @imagecreatefromstring($sourceContent) : false;

        if (! $source instanceof GdImage) {
            throw ValidationException::withMessages(['photo' => 'The selected file could not be decoded as a supported image.']);
        }

        $output = imagecreatetruecolor(self::OUTPUT_SIZE, self::OUTPUT_SIZE);
        if (! $output instanceof GdImage) {
            throw ValidationException::withMessages(['photo' => 'The profile photo could not be prepared.']);
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $sourceSide = min($sourceWidth, $sourceHeight);
        $sourceX = (int) floor(($sourceWidth - $sourceSide) / 2);
        $sourceY = (int) floor(($sourceHeight - $sourceSide) / 2);

        imagealphablending($output, true);
        $background = imagecolorallocate($output, 255, 255, 255);
        if ($background === false || ! imagefill($output, 0, 0, $background)) {
            throw ValidationException::withMessages(['photo' => 'The profile photo background could not be prepared.']);
        }

        if (! imagecopyresampled($output, $source, 0, 0, $sourceX, $sourceY, self::OUTPUT_SIZE, self::OUTPUT_SIZE, $sourceSide, $sourceSide)) {
            throw ValidationException::withMessages(['photo' => 'The profile photo could not be resized.']);
        }

        ob_start();
        $encoded = imagewebp($output, null, 88);
        $content = ob_get_clean();

        if (! $encoded || ! is_string($content) || $content === '') {
            throw ValidationException::withMessages(['photo' => 'The profile photo could not be encoded securely.']);
        }

        return [
            'content' => $content,
            'mimeType' => 'image/webp',
            'sizeBytes' => strlen($content),
            'checksum' => hash('sha256', $content),
        ];
    }
}
