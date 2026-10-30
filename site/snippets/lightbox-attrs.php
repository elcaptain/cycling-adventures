<?php
/**
 * Lightbox attributes for an <img>
 *
 * Outputs the data attributes lightbox.js uses to show a larger version.
 *
 * @var \Kirby\Cms\File $image
 * @var string|null     $caption Optional caption (falls back to the image alt text)
 */
$caption = trim((string)($caption ?? '')) ?: $image->alt()->value();
?>
data-lightbox="<?= $image->resize(2000)->url() ?>"
data-lightbox-srcset="<?= $image->srcset([800, 1200, 1600, 2000]) ?>"
<?php if ($caption): ?>data-lightbox-caption="<?= esc($caption) ?>"<?php endif ?>
