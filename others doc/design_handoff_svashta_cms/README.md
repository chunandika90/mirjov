# Handoff: Svashta Home CMS Admin Panel

## Overview
An admin CMS mockup for managing content on svashtahome.com (a bespoke fine furnishings / furniture brand). Lets an admin edit the homepage (hero carousel, video section, collaborators, client reviews, partner logos), manage blog posts, products (per category), portfolio projects, and view/report custom order requests.

## About the Design Files
The files in this bundle are **design references built as an interactive HTML prototype** (a single-file "Design Component" using a small custom templating runtime — `support.js`, not included, drives it in the design tool). They are NOT production code to copy directly. The task is to **recreate this UI in the target codebase's actual stack** (React, Vue, plain JS, etc. — whatever the project already uses, or the most sensible choice if starting fresh), using its existing component/state patterns, backed by a real backend/API instead of local component state.

`Svashta CMS.dc.html` — open directly in a browser to view/interact with the design. It contains inline `{{ }}` template placeholders and a `class Component extends DCLogic` block — treat this JS as **pseudocode for the intended state/behavior**, not code to paste in verbatim.

## Fidelity
**High-fidelity.** Colors, typography, spacing, and layout are intentional and final. Recreate pixel-for-pixel using the codebase's own component library/styling approach.

## Screens / Views
Single-page app with a persistent left sidebar (264px) + right content area. Sidebar nav switches which section renders on the right; state is client-side only in the mockup (no persistence/backend).

### Global shell
- **Sidebar** (264px wide, fixed): near-black background `#0a0a0a`, off-white text `#f7f2e8`. Top: "SVASHTA HOME" wordmark (Playfair Display, 20px, letter-spacing 2px) + "CMS ADMIN PANEL" label (11px, letter-spacing 2px, brand-gold-turned-black `#0a0a0a`... actually rendered white on dark — see Design Tokens). Nav items list below (Dashboard, Homepage, Blog, Products, Projects, Custom Orders), each a row with a small dot indicator + label; active item gets a subtle white-tinted background (`rgba(255,255,255,0.1)`) and white text/dot; inactive items are `#f7f2e8` text with a dim dot (`rgba(255,255,255,0.25)`). Bottom: small avatar circle (black bg, white "A") + "Admin" / "svashtahome.com".
- **Header** (top of content area): page title (Playfair Display, 26px) + subtitle (13px, muted), plus a "VIEW LIVE SITE ↗" outlined link button on the right linking to https://svashtahome.com/.
- **Content area**: cream background `#f7f3ec`, padded 40px/44px, scrollable.

### 1. Dashboard
- 4-up stat card grid (Total Products, Total Projects, Blog Posts, Custom Orders) — white cards, bordered, Playfair Display 38px value + small caps label.
- "Recent activity" list below — white card, bordered rows with activity text + relative time.

### 2. Homepage
Edits content shown on svashtahome.com's index page. Sections, each its own white bordered card:
- **Hero carousel**: list of slides (thumbnail, title, subtitle truncated), + "ADD SLIDE" and per-row EDIT/DELETE. Modal: slide image drop zone, title input, subtext textarea.
- **Homepage video**: video-file drop zone (placeholder for mp4), headline + slogan text inputs, save button. Maps to the site's "Watch our video" section.
- **Meet our collaborations**: grid of collaborator logo tiles (circular logo, name, EDIT/DELETE), + ADD. Modal: logo drop zone, name, link URL.
- **Client reviews**: list of reviews (avatar, name, star rating, quote, photo count), + ADD, per-row EDIT/DELETE. Modal: avatar drop, name, rating (1-5), quote textarea, and a **photo add-on gallery** (see Galleries below).
- **Partner & client logos**: grid of logo tiles with DELETE only (simple add-only modal for new ones).

### 3. Blog
List of posts (thumbnail, title, date) with EDIT/DELETE; "Add New Post" opens a modal: cover image, **in-article photo gallery** (add-on style), title, excerpt, content textareas.

### 4. Products
Filter chips across the top: All / Sofa / Table / Chair / Bed / Cabinet / Outdoor / Collections / Collaborations (matches the site's product category nav). Grid of product cards (square photo, category badge overlay, name, price, EDIT/DELETE). "Add Product" modal: cover photo, **detail gallery** (add-on), name, type select, price, materials, description, plus 3 fixed "Product Highlight" blocks (label + description each) — mirrors the feature-callout pattern used on svashtahome.com's product gallery pages (e.g. "FOLDED HEAD REST", "MEASUREMENTS", "UPHOLSTERY OPTIONS").

### 5. Projects
Grid of project cards (4:3 photo, name, location, EDIT/DELETE). "Add Project" modal: cover photo, **detail gallery** (add-on), name, location, collection select, project story textarea.

### 6. Custom Orders
4-up stat cards (Total/Pending/In Progress/Completed), "EXPORT REPORT" button, and a table of order requests (customer, contact, request, date, status pill colored by status).

## Galleries — "add-on" pattern (Reviews, Products, Projects, Blog)
Any place with multiple photos uses a compact grid of already-added photo tiles (each with a small "×" remove button top-right) followed by one dashed-border "+" tile that appends a new empty photo slot. This replaced an earlier version with a fixed block of empty upload boxes — the add-on version avoids visual clutter and keeps the modal from feeling crowded. Recreate as: an array of photo IDs in state, render an upload tile per ID + one trailing "add" tile; add appends an ID, remove filters it out.

## Interactions & Behavior
- All modals are centered overlays (`rgba(42,33,24 → now near-black,0.6)` scrim... see Design Tokens) with a max-width card, X to close.
- Edit buttons open the same modal as Add, pre-filled; Save either updates the matching item by key or appends a new one (mockup uses `Date.now()` as a temp key — replace with real IDs from the backend).
- Delete removes the item from the list immediately, no confirmation dialog in the mockup — consider adding a confirm step in production.
- Product/category filter chips: clicking sets the active filter and re-filters the grid client-side.
- "Export Report" and "Save Video Section" buttons are currently no-ops in the mockup — wire to real export/save endpoints.

## State Management
In the mockup, all content (slides, collaborators, reviews, partner logos, blog posts, products, projects, orders) lives in local component state, seeded with sample data. In production this should come from a real API/database:
- `slides[]`: { id, title, subtitle, image }
- `collaborators[]`: { id, name, image, link }
- `reviews[]`: { id, name, quote, rating, photos[] }
- `partnerLogos[]`: { id, image }
- `blogPosts[]`: { id, title, date, excerpt, content, coverImage, photos[] }
- `products[]`: { id, name, type, price, materials, description, coverImage, gallery[], highlights: [{label, text}] }
- `projects[]`: { id, name, location, collection, story, coverImage, gallery[] }
- `orders[]`: { id, name, contact, request, date, status }

## Design Tokens
- **Fonts**: Playfair Display (serif, headings/emphasis — weights 400/500/600, italic 400) + Inter (body — weights 300–600). Loaded via Google Fonts.
- **Colors**:
  - Page background: `#f7f3ec` (warm ivory)
  - Card/surface: `#ffffff`
  - Card border: `#e4d9c6`
  - Body text: `#0a0a0a` (near-black, matches svashtahome.com's dark UI elements)
  - Sidebar/dark surfaces & primary buttons: `#0a0a0a`, hover `#2a2a2a`
  - Sidebar text: `#f7f2e8`
  - Muted text: `oklch(0.5 0.02 75)` / `oklch(0.55 0.02 75)` / `oklch(0.45 0.02 75)`
  - Danger/delete: `#a3453c`
  - Order status pills: PENDING/IN PROGRESS use dark neutral tints; COMPLETED uses `#2a2a2a` tint — all desaturated to match the black-based palette (see note below)
- **Radii**: mostly square/no radius (0), small 4px radius on gallery photo tiles, full circle on avatars/collaborator logos.
- **Borders**: 1px solid `#e4d9c6` throughout for card/input outlines.
- **Note on color**: an earlier version used a warm gold/brass accent (`#c9a15c`/`#8a6a3a`); the current version was simplified to a black-forward palette to match svashtahome.com's own dark nav/footer elements, since the live site's exact CSS could not be fetched by the design tool — a developer with access to the site's real stylesheet should confirm/adjust exact hex values against production.

## Assets
No real photography is included. Product/project/slide/review/logo images are all `picsum.photos` placeholder URLs (random stock photos, seeded for consistency) — replace every image reference with real product/project photography and the actual Svashta Home collaborator/partner logos before shipping.

## Files
- `Svashta CMS.dc.html` — the full prototype (single file, view in any browser)
- `image-slot.js` — a design-tool-only helper (drag/drop image placeholder web component) that the prototype's photo tiles use; not needed in production, replace with the target stack's real upload/image component
