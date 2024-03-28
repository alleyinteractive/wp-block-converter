# Changelog

All notable changes to `WP Block Converter` will be documented in this file.

## 1.3.2

- Preserve new lines in embed blocks. They are required for proper front end rendering.
- Fix aspect ratio calculation when height and width are percentages - which fixes TikTok embeds.

## 1.3.1

- Fixes embeds of x.com urls (instead of twitter.com)

## 1.3.0

- Adds macro support to the converter, allowing for custom tags to be added to the
  converter.
- Adds support for `hr` tags.
- Adds support for embeds.

## 1.2.0

- Enhancement: Adds support for non-standard ports in sanitized URLs (e.g.,
  8080).
- Enhancement: Added new filter called `wp_block_converter_sanitized_image_url`
  which allows the image URL for converted images to be filtered before being
  applied.
- Bugfix: Changed the behavior of the `create_or_get_attachment` function to
  throw an exception instead of returning a `WP_Error`, which wasn't being
  handled previously and would result in a crash if a non-string value was
  returned by `upload_image`.
- Bugfix: Ensure `upload_image` returns the image URL.

## 1.1.0

- Expands PHP version support from just 8.0 to include 8.1 and 8.2

## 1.0.0 - 2022-12-19

- Initial release
