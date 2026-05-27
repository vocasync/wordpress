# WordPress.org listing assets

Artwork and screenshots for the WordPress.org plugin directory live here. These
are **not** shipped in the plugin ZIP — the `10up/action-wordpress-plugin-deploy`
workflow copies this directory to the SVN `assets/` folder of the listing.

Populate these post-1.0.0 (the text listing can be submitted without them):

| File                  | Size       | Purpose                                  |
| --------------------- | ---------- | ---------------------------------------- |
| `icon-256x256.png`    | 256×256    | Plugin icon (also accepts `icon.svg`)    |
| `banner-772x250.png`  | 772×250    | Listing banner (standard)                |
| `banner-1544x500.png` | 1544×500   | Listing banner (retina)                  |
| `screenshot-1.png`    | up to ~1280 wide | Matches `== Screenshots ==` item 1 in `readme.txt` (settings) |
| `screenshot-2.png`    | ″          | Editor panel                             |
| `screenshot-3.png`    | ″          | Front-end player with highlighting       |
| `screenshot-4.png`    | ″          | Inserting the VocaSync block             |

Screenshot numbers must line up with the `== Screenshots ==` list in `readme.txt`.
Capture them from the test instance (e.g. https://wordpress.ltd4.dev).
