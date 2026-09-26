<?php

declare(strict_types=1);

final class IconUpload
{
    public const MAX_BYTES = 524_288;
    public const MIN_DIMENSION = 32;
    public const MAX_DIMENSION = 512;

    /**
     * Return validated image data for an icon upload. The original image is
     * retained; the UI scales it to each icon container with object-fit:contain.
     * Returns null when no file was provided.
     *
     * @return array{data:string,mime:string}|null
     */
    public static function fromUpload(?array $file): ?array
    {
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new InvalidArgumentException('The icon upload could not be read.');
        }

        $path = (string) $file['tmp_name'];
        $size = filesize($path);
        if (!is_file($path) || $size === false || $size > self::MAX_BYTES) {
            throw new InvalidArgumentException('Icons must be no larger than 512 KiB.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detected = $finfo->file($path);
        $mime = match ($detected) {
            'image/png' => 'image/png',
            'image/jpeg' => 'image/jpeg',
            'image/webp' => 'image/webp',
            'image/x-icon', 'image/vnd.microsoft.icon', 'application/ico', 'application/x-ico' => 'image/x-icon',
            default => null,
        };
        if ($mime === null) {
            throw new InvalidArgumentException('Use a PNG, JPEG, WebP, or ICO image.');
        }

        if ($mime === 'image/x-icon') {
            self::validateIco($path, (int) $size);
        } else {
            $image = @getimagesize($path);
            if (!$image || $image['mime'] !== $mime || $image[0] < self::MIN_DIMENSION || $image[1] < self::MIN_DIMENSION
                || $image[0] > self::MAX_DIMENSION || $image[1] > self::MAX_DIMENSION || $image[0] !== $image[1]) {
                throw new InvalidArgumentException('Icons must be square and between 32 × 32 and 512 × 512 pixels.');
            }
        }

        $bytes = file_get_contents($path);
        if ($bytes === false) throw new InvalidArgumentException('The icon upload could not be read.');
        return ['data' => base64_encode($bytes), 'mime' => $mime];
    }

    private static function validateIco(string $path, int $size): void
    {
        $bytes = file_get_contents($path);
        if ($bytes === false || $size < 22) {
            throw new InvalidArgumentException('The uploaded ICO file is invalid.');
        }

        $header = unpack('vreserved/vtype/vcount', substr($bytes, 0, 6));
        $count = (int) ($header['count'] ?? 0);
        if (($header['reserved'] ?? 1) !== 0 || ($header['type'] ?? 0) !== 1 || $count < 1 || $count > 32 || $size < 6 + 16 * $count) {
            throw new InvalidArgumentException('The uploaded ICO file is invalid.');
        }

        $hasUsableFrame = false;
        for ($i = 0; $i < $count; $i++) {
            $entry = unpack('Cwidth/Cheight/Ccolors/Creserved/vplanes/vbits/Vlength/Voffset', substr($bytes, 6 + 16 * $i, 16));
            $width = $entry['width'] === 0 ? 256 : (int) $entry['width'];
            $height = $entry['height'] === 0 ? 256 : (int) $entry['height'];
            $length = (int) $entry['length'];
            $offset = (int) $entry['offset'];
            if ($width < 16 || $height < 16 || $width > 256 || $height > 256 || $width !== $height
                || $length <= 0 || $offset < 6 + 16 * $count || $offset + $length > $size) {
                throw new InvalidArgumentException('ICO files must contain square frames from 16 × 16 to 256 × 256 pixels.');
            }
            if ($width >= self::MIN_DIMENSION) $hasUsableFrame = true;
        }
        if (!$hasUsableFrame) throw new InvalidArgumentException('The ICO file must include at least one frame of 32 × 32 pixels or larger.');
    }
}
