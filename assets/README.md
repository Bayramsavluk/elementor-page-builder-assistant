# WordPress Plugin Assets

This folder contains visual assets for the WordPress.org plugin directory listing.

## Required Assets for WordPress.org

### Plugin Icon
- **icon-128x128.png** - Small icon (128x128 px)
- **icon-256x256.png** - Large icon (256x256 px, recommended for Retina displays)

### Plugin Banner
- **banner-772x250.png** - Standard banner (772x250 px)
- **banner-1544x500.png** - Retina banner (1544x500 px, recommended)

### Screenshots
Place numbered screenshots for the readme.txt:
- **screenshot-1.png** - Pages management interface with bulk import functionality
- **screenshot-2.png** - Add multiple pages at once with template selection
- **screenshot-3.png** - Header & Footer import with translation options
- **screenshot-4.png** - Translation API configuration settings
- **screenshot-5.png** - Import progress with real-time feedback
- **screenshot-6.png** - Bulk actions for managing imported content

## Design Guidelines

### Brand Colors
- **Primary**: #5B4FE9 (Modern purple/indigo - represents creativity and technology)
- **Secondary**: #00D4AA (Turquoise/mint - represents growth and innovation)
- **Accent**: #FF6B6B (Coral red - represents action and energy)
- **Dark**: #1A1A2E (Deep navy - represents professionalism)
- **Light**: #F8F9FA (Off-white - represents clarity)

### Design Concept
The logo should convey:
- **Speed & Efficiency** - Quick bulk operations
- **Translation** - Multi-language support
- **Elementor Integration** - Connection to Elementor ecosystem
- **Professional** - For developers and agencies

### Logo Concept: "EP" Monogram with Translation Symbol
```
Design Elements:
- Stylized "EP" monogram (Elementor Page Builder Assistant)
- Abstract translation arrows or language symbol integrated
- Modern, geometric, minimalist style
- Gradient from primary to secondary color
- Clean sans-serif typography for "Shav Code" if included
```

### Icon Mockup Description
**Center Element**: 
- Overlapping "E" and "P" letters in a modern geometric style
- The "E" has three horizontal bars (representing bulk/multiple items)
- The "P" is slightly rotated, creating dynamic movement
- Between the letters: small translation arrows (⇄) or globe symbol

**Color Treatment**:
- Gradient from #5B4FE9 (top-left) to #00D4AA (bottom-right)
- White icon on colored circular background for contrast
- Subtle drop shadow for depth

**Background**:
- Circular or rounded square shape
- Solid gradient or soft radial gradient

### Banner Mockup Description
**Layout**:
- Left side: Large version of the icon/logo
- Right side: Plugin name and tagline
- Background: Subtle abstract pattern with Elementor-style geometric shapes

**Text**:
- "Elementor Page Builder Assistant" in bold, modern sans-serif
- Tagline: "Bulk Import Templates • Auto-Translate • Save Time"
- "by Shav Code" in smaller text

**Background Pattern**:
- Faded geometric shapes suggesting pages/templates
- Subtle grid or connection lines representing workflow
- Gradient overlay for visual interest

## Creating Assets

### Tools You Can Use:
1. **Figma** - Professional design tool (free tier available)
2. **Canva** - Easy-to-use design platform
3. **Adobe Photoshop/Illustrator** - Industry standard
4. **Inkscape** - Free vector graphics editor
5. **GIMP** - Free raster graphics editor

### Quick Tips:
- Use PNG format with transparency for icons
- Use PNG or JPG for banners (PNG for transparency)
- Optimize images for web (use TinyPNG or similar)
- Keep file sizes reasonable (under 300KB per image)
- Test on both light and dark WordPress admin themes
- Ensure text is readable at smaller sizes

## AI Image Generation Prompts

If using AI tools like DALL-E, Midjourney, or similar:

### Icon Prompt:
```
Modern minimalist logo icon for Elementor WordPress plugin, featuring stylized "EP" monogram with translation arrows, geometric design, gradient from purple #5B4FE9 to turquoise #00D4AA, professional tech style, clean lines, circular background, flat design, vector style
```

### Banner Prompt:
```
WordPress plugin banner header, left side has circular purple-to-turquoise gradient icon with "EP" monogram, right side has "Elementor Page Builder Assistant" title, modern tech aesthetic, abstract geometric shapes in background, professional developer tool design, 1544x500 pixels, clean minimalist style
```

## File Naming Convention

WordPress.org expects exact filenames:
- ✅ `icon-128x128.png`
- ✅ `icon-256x256.png`
- ✅ `banner-772x250.png`
- ✅ `banner-1544x500.png`
- ✅ `screenshot-1.png` (and so on)

❌ Do NOT use: `icon.png`, `banner.jpg`, `Icon-256.png`, etc.

## Uploading to WordPress.org

Assets are uploaded via SVN to a special `.wordpress.org` directory:
```
svn co https://plugins.svn.wordpress.org/elementor-page-builder-assistant/
cd elementor-page-builder-assistant
mkdir assets
# Add your images to the assets folder
svn add assets/*
svn ci -m "Add plugin assets"
```

## References

- WordPress Plugin Assets Guide: https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/
- Design Inspiration: Browse top Elementor addons on WordPress.org
- Icon Design: Keep it simple, recognizable at 32x32 pixels
