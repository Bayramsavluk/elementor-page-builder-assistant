=== Elementor Page Builder Assistant ===
Contributors: shavcode
Donate link: https://www.patreon.com/shavcode
Tags: elementor, template kit, bulk import, header footer, translation
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Streamline your Elementor workflow by bulk importing template kits to pages and header/footer sections with built-in multi-language translation support.

== Description ==

**Elementor Page Builder Assistant** is a powerful productivity tool designed for WordPress developers, designers, and agencies who work extensively with Elementor and Template Kits. This plugin dramatically reduces the time spent on repetitive tasks by enabling bulk operations and automatic content translation.

= Key Features =

**Bulk Template Import**
* Import multiple Elementor templates to pages in a single operation
* Add rows dynamically to create multiple pages at once
* Automatically apply template kits from your library
* Set page templates, parent pages, and other WordPress page attributes in bulk

**Header & Footer Management**
* Import template kit sections as header, footer, or before-footer layouts
* Seamless integration with popular header/footer plugins
* Configure display rules and location targeting
* Support for Elementor Canvas template layouts

**Built-in Translation Engine**
* Translate imported templates on-the-fly during import
* Support for multiple translation APIs: MyMemory, LibreTranslate, DeepL, Microsoft Translator, Yandex Translate
* Translate to 11+ languages including Turkish, English, German, French, Spanish, Italian, Portuguese, Russian, Arabic, Chinese, and Japanese
* Preserves Elementor structure while translating text content
* Smart content detection for headings, paragraphs, buttons, and widgets

**User-Friendly Interface**
* Clean, intuitive admin interface integrated with WordPress
* Real-time import progress indicators
* Batch processing with visual feedback
* Filter and manage imported content easily

= Who Is This For? =

**Web Developers & Designers**
* Quickly prototype client websites using template kits
* Create multilingual sites without manual translation work
* Streamline your development workflow

**Digital Agencies**
* Scale your client onboarding process
* Deliver multilingual websites faster
* Reduce repetitive manual work

**Freelancers & Solopreneurs**
* Take on more projects with less time investment
* Offer multilingual services without additional overhead
* Focus on creative work instead of repetitive tasks

**Template Kit Creators**
* Test your templates across multiple pages quickly
* Demonstrate multilingual capabilities to clients
* Speed up quality assurance processes

= Requirements =

* WordPress 5.0 or higher
* Elementor (free version works, Pro recommended)
* Template Kit Import plugin (for importing templates from Envato Elements)
* PHP 7.0 or higher

= Translation API Setup =

The plugin supports multiple translation providers. Configure your preferred API in Settings:

1. **MyMemory Translation** - Free tier available, no API key required for basic usage
2. **LibreTranslate** - Open-source, self-hosted option available
3. **DeepL** - Professional quality translations (API key required)
4. **Microsoft Translator** - Enterprise-grade translation (API key required)
5. **Yandex Translate** - Reliable translation service (API key required)

= Privacy & Data =

* Translation is performed through third-party APIs - check your chosen provider's privacy policy
* No data is collected or stored by this plugin
* Template imports remain on your WordPress installation

= Support & Contributions =

Found a bug? Have a feature request? Visit our GitHub repository or support forums.

If you find this plugin helpful, please consider supporting development on Patreon: https://www.patreon.com/shavcode

== Installation ==

= Automatic Installation =

1. Log in to your WordPress admin panel
2. Navigate to Plugins > Add New
3. Search for "Elementor Page Builder Assistant"
4. Click "Install Now" and then "Activate"

= Manual Installation =

1. Download the plugin zip file
2. Navigate to Plugins > Add New > Upload Plugin
3. Choose the downloaded file and click "Install Now"
4. Activate the plugin through the Plugins menu

= Post-Installation Setup =

1. Install and activate Elementor (if not already installed)
2. Install Template Kit Import plugin (for template kit functionality)
3. Go to Elementor Import > Settings to configure translation APIs (optional)
4. Import some template kits through Template Kit Import plugin
5. Start using the bulk import features from Elementor Import menu

== Frequently Asked Questions ==

= Does this plugin work without Elementor Pro? =

Yes, the plugin works with the free version of Elementor. However, Elementor Pro unlocks additional features like theme builder and advanced widgets that you may want to use with imported templates.

= Which template kits are supported? =

Any template kit compatible with the Template Kit Import plugin will work. This includes template kits from Envato Elements and other compatible sources.

= How does the translation feature work? =

The plugin parses Elementor's JSON structure and identifies translatable text content (headings, paragraphs, buttons, etc.). It then sends this content to your configured translation API and updates the template with translated text while preserving all design elements and structure.

= Is translation free? =

Some translation APIs offer free tiers (like MyMemory), while others require paid API keys (DeepL, Microsoft, Yandex). LibreTranslate can be self-hosted for unlimited free translations.

= Can I translate existing pages? =

Currently, the plugin only translates content during the import process. Translation of existing pages may be added in a future update.

= Does this work with header/footer plugins? =

Yes, the plugin is compatible with popular header/footer plugins like Header Footer Elementor and similar solutions that use the 'elementor-hf' post type.

= What happens to imported templates? =

Imported templates become regular WordPress pages or elementor-hf posts in your database. They can be edited, deleted, or managed just like any manually created content.

= Can I undo an import? =

Yes, imported pages can be moved to trash or permanently deleted through the plugin's interface. The plugin marks imported content with metadata for easy identification.

= Does this plugin slow down my site? =

No, the plugin only runs in the WordPress admin area and has no impact on your site's frontend performance. Import operations are performed as background tasks.

= Can I contribute to development? =

Absolutely! Visit our GitHub repository to submit issues, feature requests, or pull requests. We welcome community contributions.

== Screenshots ==

1. Pages management interface with bulk import functionality
2. Add multiple pages at once with template selection
3. Header & Footer import with translation options
4. Translation API configuration settings
5. Import progress with real-time feedback
6. Bulk actions for managing imported content

== Changelog ==

= 1.0.0 - 2025-11-16 =
* Initial release
* Bulk page import from template kits
* Header/Footer template import
* Multi-language translation support (11+ languages)
* Support for 5 translation APIs
* Batch processing interface
* Display rules and location targeting
* WordPress 6.8 compatibility
* Elementor 3.20 compatibility

== Upgrade Notice ==

= 1.0.0 =
Initial release of Elementor Page Builder Assistant. Welcome aboard!

== Additional Information ==

= Documentation =

For detailed documentation, visit: https://github.com/shavcode/elementor-page-builder-assistant/wiki

= Support =

For support requests, please use the WordPress.org support forums or visit our GitHub issues page.

= Contributing =

Want to contribute? Visit: https://github.com/shavcode/elementor-page-builder-assistant

= Donate =

If you find this plugin valuable, please consider supporting continued development: https://www.patreon.com/shavcode

Your support helps maintain and improve this free plugin for the WordPress community.
