# lubov-vera Child

Minimal child theme of `lubov-vera`. Just enqueues the parent stylesheet and adds
a **Font Library bridge**.

## Fonts

The site font is uploaded via the WordPress **Font Library** (Site Editor → Fonts).

- Stored as `wp_font_family` + `wp_font_face` posts; the file is copied to
  `wp-content/uploads/fonts/` (e.g. `gilroy-regular.ttf`).
- WordPress core prints the `@font-face` into `<style class="wp-fonts-local">`.
- The **parent** theme is a classic theme and hardcodes `font-family: "Inter"`,
  which would otherwise hide the uploaded font (classic themes do not apply
  Font Library fonts automatically).

`functions.php` → “FONT LIBRARY BRIDGE” applies the Font Library font
**site-wide** (`body`, `.page-title`, `h1`–`h6`) as an enqueued inline style
placed after the theme CSS.

- Applied family: explicit, matched by name, default `Gilroy`.
- Change it with the `lv_site_font_family` filter, e.g.:

  ```php
  add_filter( 'lv_site_font_family', fn() => 'My Font' );
  ```

- If the named family is not in the Font Library, nothing is printed and the
  theme keeps its own font (no silent fallback to an arbitrary font).
- Fallback stack is the **system** stack — `"Inter"` is intentionally excluded
  because it is not loaded anywhere on this site.

## Migration

When moving the site, copy **both**:

1. the database records (`wp_font_family` / `wp_font_face` posts + global styles), and
2. the font file in `wp-content/uploads/fonts/`.

Copying only the file will not render. After deploy, purge any page/CSS cache.
