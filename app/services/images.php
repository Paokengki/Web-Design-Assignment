<?php
/**
 * Image paths and safe uploads.
 * Café photos live at material/<Café name>/shop.jpg, dishes at material/<Café name>/<Dish name>.jpg
 * (the same layout the old pages used). Avatars go to material/images/uploads/.
 */

const MAX_UPLOAD_BYTES = 3 * 1024 * 1024;

function material_dir(): string
{
	return dirname(__DIR__, 2) . '/material';
}

/** Strip characters Windows/Linux cannot use in folder or file names. */
function safe_name(string $name): string
{
	$safe = trim(preg_replace('/[\\\\\/:*?"<>|]+/', '', $name));
	return $safe !== '' ? $safe : 'item_' . bin2hex(random_bytes(3));
}

function cafe_image_url(string $cafe): string
{
	return url('material/' . rawurlencode(safe_name($cafe)) . '/shop.jpg');
}

function food_image_url(string $cafe, string $food): string
{
	return url('material/' . rawurlencode(safe_name($cafe)) . '/' . rawurlencode(safe_name($food)) . '.jpg');
}

function cafe_dir(string $cafe): string
{
	$dir = material_dir() . '/' . safe_name($cafe);
	if (!is_dir($dir)) mkdir($dir, 0755, true);
	return $dir;
}

/** Keep the image folder in step when a café is renamed. */
function rename_cafe_dir(string $old, string $new): void
{
	$from = material_dir() . '/' . safe_name($old);
	$to = material_dir() . '/' . safe_name($new);
	if ($from !== $to && is_dir($from) && !is_dir($to)) rename($from, $to);
}

/**
 * Validate an uploaded image and re-encode it as JPEG with GD.
 * Re-encoding drops anything that is not pixel data, so a disguised script cannot be uploaded.
 * Returns null on success or an error message.
 */
function save_uploaded_jpeg(array $file, string $dest, int $maxSide = 1600): ?string
{
	if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
		return ($file['error'] ?? 0) === UPLOAD_ERR_INI_SIZE ? 'Image is too large.' : 'Upload failed. Try another image.';
	}
	if ($file['size'] > MAX_UPLOAD_BYTES) return 'Image must be under 3 MB.';
	$info = @getimagesize($file['tmp_name']);
	$readers = ['image/jpeg' => 'imagecreatefromjpeg', 'image/png' => 'imagecreatefrompng', 'image/webp' => 'imagecreatefromwebp', 'image/gif' => 'imagecreatefromgif'];
	if (!$info || !isset($readers[$info['mime']])) return 'Use a JPG, PNG, WEBP or GIF image.';

	$src = @$readers[$info['mime']]($file['tmp_name']);
	if (!$src) return 'That image could not be read.';
	[$w, $h] = [imagesx($src), imagesy($src)];
	$scale = min(1, $maxSide / max($w, $h));
	$nw = max(1, (int) round($w * $scale));
	$nh = max(1, (int) round($h * $scale));
	$out = imagecreatetruecolor($nw, $nh);
	imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255)); // flatten transparency onto white
	imagecopyresampled($out, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
	$ok = imagejpeg($out, $dest, 86);
	imagedestroy($src);
	imagedestroy($out);
	return $ok ? null : 'Could not save the image.';
}
