# Certrxy — Certificate Generation & PDF Processing Utility

**Laravel 12 • PHP 8.3+ • MySQL/XAMPP • Local-first certificate generation**

Certrxy creates certificates from imported PDF/PNG/JPG templates, recipient names, and customizable text or signature fields. It includes font management, CSV/TXT and supported spreadsheet recipient imports, batch generation, PDF downloads, print sheets, batch/certificate trash, and portable template export/import. A system-wide Backup & Restore interface is intentionally not included. The project supports a Laravel browser application using MySQL/XAMPP and an optional NativePHP Windows desktop build track with a separately configured SQLite environment. **The Laravel source project is not a prebuilt Windows installer.**

## Project naming and compatibility

**Certrxy** is the current application and project name; **Certify** was the former name. Some technical identifiers in older source versions may intentionally keep the original name for compatibility. Do **not** rename these blindly without updating and testing the implementation:

- Artisan commands: `php artisan certify:pdf:doctor` and `php artisan certify:bundle-templates`
- Configuration variable: `CERTIFY_QPDF_BINARY`
- Resource directory: `resources/certify/bundled/templates/`
- Template package extension: `.certify-template`
- Existing database name (if still configured): `certify_db`

These are **legacy internal identifiers**, not the public brand. They should be changed only as part of a tested code/database migration. The project directory shown below is `C:\xampp\htdocs\certrxy`; adjust it if your local checkout is elsewhere.

## Start Certrxy on Windows with XAMPP / MySQL

1. Install XAMPP, PHP 8.3/8.4 CLI, and Composer. Verify `php -v`, `php -m` and `composer --version`. PHP needs `pdo_mysql`, `mbstring`, `fileinfo`, `zip`, `openssl`, `xml`, `dom`, and other Composer-required extensions.
2. Unzip the project as `C:\xampp\htdocs\certrxy` (avoid nesting an extra `certrxy` directory). Start **MySQL** in XAMPP; start Apache if you plan to access the project through Apache.
3. Open PowerShell in the project directory and install dependencies:

   ```powershell
   cd C:\xampp\htdocs\certrxy
   composer install
   php artisan --version
   ```

4. **Private full ZIP only:** the original `.env` may be retained. The previously documented configuration used `DB_CONNECTION=mysql` and `DB_DATABASE=certify_db`. Confirm your actual `.env` settings and database name; the visible app branding does not require renaming your MySQL database. Check your own MySQL username/password and ensure the matching database exists. Do not publicly share `.env`. **Source-only ZIP:** make a local `.env` first with `Copy-Item .env.example .env`, set MySQL fields to your own values and run `php artisan key:generate` **only for a brand-new installation**. Never regenerate the key on an existing installation.
5. Prepare the database and run locally:

   ```powershell
   php artisan migrate
   php artisan optimize:clear
   php artisan serve --host=127.0.0.1 --port=8000
   ```

   Open <http://127.0.0.1:8000>. You may also configure an Apache virtual host with its document root pointing to Certrxy's **`public`** folder; never serve the Laravel project root directly. The public front controller is `public/index.php`.

**Historical dependency note (17 October 2026):** The earlier supplied `composer.json` requires `nativephp/desktop`, but the original `composer.lock` did not list that package. If Composer stops with `Required package "nativephp/desktop" is not present in the lock file`, run `composer update nativephp/desktop --with-all-dependencies` in your PHP 8.4 Windows environment to regenerate the lockfile. Do not run Artisan until Composer succeeds and `vendor/autoload.php` exists. This issue was recorded in the earlier project package; it may already be resolved in newer versions. Preserve the corrected lockfile after resolving it.

**Important:** Run `composer install` if third-party `vendor/` files are absent from your source package. Browser-facing `public/css/` and `public/js/` files are retained and do not require a Vite build to launch this version. PHP unit tests require extra PHP extensions (`dom`, `mbstring`, `xmlwriter`).

## Existing projects, templates and databases

**Previously documented full project ZIP:** preserves the uploaded `.env`, `database/database.sqlite`, private template files, generated certificates, signatures and installed fonts. It removes only generated logs/Blade caches/converter caches and redundant files. Your original uploaded archive is unchanged.

**Previously documented source-only ZIP:** excludes private user data, `.env`, `database/database.sqlite`, generated backups and `vendor/`. It is intended for sharing or clean installation, **not** as a replacement for your working data folder. To update an existing installation, back up the database and `storage/app/private/` before replacing code; restore the original private files to the new project if needed. A MySQL database is **outside** the ZIP; a ZIP does not back up XAMPP's running MySQL database. Export MySQL separately using phpMyAdmin or `mysqldump`.

The existing `database/database.sqlite` is a legacy/local SQLite file, **not** the MySQL database selected by the current `.env`. Do not delete existing databases or private files unless you have a verified backup. Do not run `migrate:fresh`, `db:wipe`, or any destructive reset on a live installation.

## PDF compatibility

Certrxy uses TCPDF and FPDI (Composer packages) for personalized PDFs. Some advanced PDF templates need qpdf for compatibility. The previously documented package includes the **Windows qpdf runtime** (`resources/bin/win-x64/qpdf/bin/`) plus its upstream license and acknowledgement pages, not the unnecessary development headers, libraries or extensive manuals. Keep its DLL files beside `qpdf.exe`. The desktop build script can verify or restore the pinned qpdf distribution. On non-Windows systems install qpdf separately and ensure it is in PATH or configured in `CERTIFY_QPDF_BINARY`.

Check PDF support with:

```powershell
php artisan certify:pdf:doctor
```

Test PDF output visually before bulk generation and check source artwork permissions, embedded fonts, margins, and print sizing.

## Portable template packages and built-in designs

The **Templates** page now offers **Import**, and each non-SVG template's options menu includes **Export package**. The exported `.certify-template` package is a ZIP archive containing a versioned `manifest.json` (template name, page size, editable fields) and original PDF/PNG/JPG design artwork. Packages have a SHA-256 artwork check and strict file validation, must be at most 25 MB, and can only be imported/exported on `APP_ENV=local` from the local computer (`127.0.0.1` or `::1`). Imported templates are saved as **new** templates, never overwrite existing ones, and open in the editor for review. This feature requires PHP `zip` and `fileinfo` extensions.

The package intentionally does **not** carry recipients, certificate records, issued PDFs, custom font binaries, or handwritten signature image files. Signature fields need to be re-added and approved by their owner. Installed custom fonts absent on the destination are mapped to DejaVu Sans; review visual output before generating certificates. Exporting designs you do not own or are not authorized to distribute remains your responsibility.

The Templates page initially includes **four generated built-in designs** (academic, modern recognition, formal appreciation, participation). Once your actual uploaded templates are captured using `php artisan certify:bundle-templates`, the built-in gallery shows **your packaged templates instead**; it keeps their source artwork and editable fields. You must run the capture command on the PC that has the current MySQL records; a source ZIP cannot infer your current active template rows from an unrelated legacy SQLite database. Choosing a design creates an independent, editable PDF artwork and recipient-name field using TCPDF; no bundled certificate image files are necessary. The Font Library already includes six PDF-compatible built-in families (DejaVu Serif, DejaVu Sans, DejaVu Mono, Times, Helvetica, Courier). The existing optional curated and uploaded font installation features remain available; optional fonts require separate files, licensing permissions, and may require internet. The 17 October 2026 package documented ten bundled, OFL-licensed TrueType fonts (Great Vibes, Allura, Sacramento, Italianno, Marcellus, Old Standard TT, Prata, Abril Fatface, Play, Basic), their PDF definitions and copyright/OFL notices. In that package, they work offline on a fresh copy without installation or downloads. On an existing installation, locally installed copies take precedence so saved names and additional font styles remain intact. JuliaMetal is **not** bundled because its supplied registry did not contain a redistributable license.

**Removing system-wide Backup & Restore does not remove prior files, the MySQL database, certificates, templates or user data.** Template packages are not database backups. Keep independent system-level recovery copies of MySQL and `storage/app/private/` when migrating PCs or deploying an installer.


## Make your OWN uploaded templates permanent built-in defaults

A source-only ZIP cannot recover names and exact editable elements of templates stored in an active **MySQL** database. The old SQLite database included with an earlier project archive is not the current MySQL database. The earlier package did not automatically bundle templates kept only in the live database. It provided a repeatable capture command.

On the Windows computer containing your current Certrxy **MySQL** database and `storage/app/private` template artwork, after Composer has installed successfully, run:

```powershell
cd C:\xampp\htdocs\certrxy
php artisan optimize:clear
php artisan certify:bundle-templates
```

The command captures **all active** MySQL templates by default. To capture selected templates only, pass `--ids=1,2,3` with the actual IDs; for a deliberate replacement use `--force`. It does not alter or delete source templates, databases, users or generated certificates, and it **does not include handwritten signature images**. It may include fixed text, names/logos, and embedded artwork—review ownership and privacy before redistribution.

This produces `resources/certify/bundled/templates/manifest.json` plus the original PDF/PNG/JPG artwork under the same directory. **Include that entire folder** in a future source ZIP, installer, or desktop build. On any new Certrxy installation, the bundled certificates automatically appear in the **Built-in designs** gallery (no uploads needed); **Use design** makes an editable copy. This workflow does not automatically populate the active MySQL templates table or issue certificates. PDF/browser preview requires your app to run on localhost. For any generated installer, ensure the resources directory is retained by the packager.

To ensure these assets were captured, check the generated `manifest.json`. For help combining the folder into a production release, share the manifest/artwork bundle (only if you are authorized to distribute its content). Never share the `.env` or MySQL dump publicly.

## Optional NativePHP Windows desktop track

The Laravel source includes NativePHP integration and helper scripts. **A working development build does not by itself establish that an installer has passed clean-machine release testing.** Its existing scripts expect a **separate SQLite desktop environment**. Do not change your working MySQL `.env` in place unless it has been backed up.

```powershell
.\desktop\Setup-Desktop.ps1
php artisan native:run
# Only on the separate, prepared desktop build environment:
.\desktop\Build-Windows.ps1
```

Prerequisites: Windows, Node.js 22+, PHP 8.3+ with `pdo_sqlite` and `sqlite3`, Composer, compatible NativePHP build dependencies and a distinct desktop `.env` based on `.env.desktop.example`. The helper `Setup-Desktop.ps1` has an optional `-UseDesktopEnvironment` switch that **replaces `.env` after backing it up**; do not use it casually on the working MySQL project. Build script requires `NATIVEPHP_APP_VERSION` and validated qpdf. Test installation, local storage persistence, printing, and upgrades on a clean Windows machine before distribution. Do not assume a desktop installer uses the MySQL database from XAMPP.

## Commands and quality checks

```powershell
php artisan route:list
php artisan test
php artisan certify:pdf:doctor
php artisan optimize:clear
```

For the earlier 17 October 2026 package, the ZIP was checked for archive integrity, asset references, Laravel route registration and PHP syntax. **The automated PHPUnit suite could not execute in that cleanup environment because PHP `dom`, `mbstring` and `xmlwriter` extensions were unavailable.** Route registration is not proof that database operations, certificate rendering, Windows installer packaging, or end-to-end workflows were tested.

## Safety and deployment limits

- The Laravel web build described in the original README had **no login/authentication layer** for all certificate actions; recheck this against your current code before deployment. Use only on trusted local devices and a loopback-bound service. Do **not** expose the web application directly to the public Internet or an untrusted network.
- `.env`, uploads, recipient data, generated PDFs and any locally saved databases contain potentially sensitive information. Share only the source-only archive when collaborating publicly.
- PDF imports may not accept encrypted or advanced PDFs without conversion. Keep originals.
- The system-wide Backup & Restore pages, routes, controllers and Artisan commands have been removed. Maintain independent MySQL/database and private-file backups outside Certrxy.
- The legal page is informative, not a legal certification. Document who owns certificate designs, signatories, logos and fonts, and validate permissions to process recipients' data. Never assume included artwork is licensed for redistribution.

## Credits, licenses and third-party notices

**Project:** Certrxy — Laravel-Based Automated Certificate Generation and PDF Processing Utility.

**Developer / project credits:** George Rexy Vincent Z. Bacani. Respect other collaborators' and contributors' authorship where applicable; do not imply exclusive ownership of third-party software or uploaded artwork.

Dependencies include Laravel, NativePHP Desktop, FPDI, TCPDF, Tailwind CSS, and qpdf. See `composer.json`, `composer.lock` and `package.json` for dependency versions, and the original license files that Composer/npm install inside their packages. The redistributed qpdf Windows executable and DLLs are governed by qpdf's upstream license; its original license and acknowledgements are retained under `resources/bin/win-x64/qpdf/share/doc/qpdf/manual-html/` (see `license.html` and `acknowledgement.html`). Third-party libraries, font families, logos and user-supplied templates may have distinct permissions; maintain an asset register with source, owner, terms, date and proof of permission. **The project README does not replace third-party legal licenses.**

## Historical cleanup and feature summary (17 October 2026)

- Combined old project changelogs, upgrades, release notes and troubleshooting documents into this **one project README**.
- Omitted `vendor/` from the distributable package so it can be reinstalled from the preserved Composer lockfile.
- Removed obsolete route-inaccessible verification, legal review and welcome pages and unused historic CSS/JS files.
- Corrected a stale in-app reference to removed legal documents and replaced tests that referenced obsolete legal-review routes.
- Removed Laravel-generated logs, cached views/package manifests, transient locks and PDF conversion caches.
- Trimmed bundled qpdf to Windows executables/DLLs and upstream legal notices, retaining the required offline PDF conversion runtime.
- Preserved all current Laravel controllers, public-facing routes, models, database migrations, working frontend assets, tests (except obsolete ones), NativePHP build helpers and user data in the **private full project** archive.

**Keep a verified backup of your working installation, MySQL database, and private storage before installing updates.**

### Template exchange and starter designs update (17 October 2026)

- Removed system-wide Backup & Restore routes/controllers/views/services/tests and sidebar entry without deleting existing user data.
- Added local-only `.certify-template` artwork + editable-field export/import and ZIP integrity validation.
- Added four built-in, customizable PDF certificate designs created only when selected.
- Retained the six standard built-in PDF-compatible fonts and the optional font installer; no external font binaries were repackaged.
- Added new feature tests, with ZIP/PDF rendering dependent on a compatible Windows PHP runtime.

### Actual uploaded templates and fonts as built-in resources (17 October 2026)

- Bundled ten installed OFL typefaces (TTF + compiled PDF definitions) with stable font IDs and notices; available offline to new installations.
- Left JuliaMetal unbundled due to missing redistribution license.
- Added an explicit Artisan command to snapshot all active templates from your **current MySQL** installation into the source's built-in gallery, including artwork and editable text/line elements.
- Excluded handwritten signature image elements from portable built-ins. Existing active templates and MySQL data are untouched.
- Shown a real artwork preview for captured built-in entries; every click creates a separately editable copy.
- Kept **one README.md**; an OFL license and font attribution files are redistributed as mandatory third-party notices, not additional project READMEs.
