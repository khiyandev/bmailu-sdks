# Bmailu for WordPress

A WordPress plugin that sends all WordPress email (`wp_mail()`) through the Bmailu Email API.
It hooks `pre_wp_mail` (WordPress 5.7+), so every plugin that uses `wp_mail()` works unchanged.

- Plugin: `bmailu/` (main file `bmailu/bmailu.php`, wordpress.org readme `bmailu/readme.txt`, license GPLv2 or later).
- Settings: Settings → Bmailu (API key, From email, From name, fallback, "Send test email").
- The `BMAILU_API_KEY` constant in `wp-config.php` wins over the saved key and hides the field.
- Filter `bmailu_api_base_url` (default `https://app.bmailu.com/api/v1`) points the plugin at another API.
- Docs: https://bmailu.com/docs/

Note: the other SDKs in this folder are MIT. This plugin is GPLv2 or later, because wordpress.org requires a GPL-compatible license and WordPress plugins are usually GPL.

## Tests

```sh
./test.sh
```

It runs `php -l` on every PHP file, starts the shared local API (`../test-server.js`), and runs `tests/run.php`.
`tests/wp-stub.php` is a minimal WordPress stand-in (hooks, options, `wp_remote_post` over PHP streams), so no WordPress install is needed.

## Build the zip

```sh
cd sdks/wordpress && zip -r bmailu.zip bmailu
```

Install it on a test site with Plugins → Add New → Upload Plugin, then send a test email from Settings → Bmailu.

## Submitting to wordpress.org (the operator does this)

1. Log in to wordpress.org with the account that will own the plugin. Its username should be `bmailu`,
   because readme.txt lists `Contributors: bmailu` (change that line if the username differs).
2. Optional but recommended: run the "Plugin Check" plugin on a test site with the plugin active and fix what it reports.
3. Upload `bmailu.zip` at https://wordpress.org/plugins/developers/add/ . The slug comes from the plugin name (`bmailu`).
4. A volunteer reviews it by hand (usually days to a few weeks). Answers come by email to the account address;
   reply to that email, do not resubmit.
5. After approval you get an SVN repository (`https://plugins.svn.wordpress.org/bmailu/`). Put the plugin files in
   `trunk/`, copy them to `tags/0.1.0/`, and commit with your wordpress.org username and SVN password
   (set on your profile). Banners, icon and screenshots go in the top-level `assets/` folder.
6. Each new release: raise `Version` in `bmailu.php` and `Stable tag` in `readme.txt`, add a Changelog entry,
   commit to `trunk/` and add a new `tags/<version>/`.
