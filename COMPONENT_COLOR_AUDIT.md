# DineMate Component and Color Audit

## Overview
This audit identifies all HTML/PHP components across the DineMate codebase and documents their current color usage, styling patterns, and hardcoded styles.

---

## 1. DESIGN SYSTEM - Color Tokens (CSS Variables)

**Location**: [assets/css/app.css](assets/css/app.css) (Lines 1-60)

### Base Colors
- `--dm-bg: #f4f5f0` - Page background (olive 50)
- `--dm-surface: #ffffff` - Card/surface background (white)
- `--dm-surface-muted: #e6eadd` - Muted surface (olive 100)
- `--dm-white: #ffffff` - Inverted text / pure white utility
- `--dm-black: #1a1f14` - Deep olive text utility

### Text Colors
- `--dm-text: #1a1f14` - Primary text (olive 950)
- `--dm-text-muted: #515d3f` - Secondary text (olive 700)
- `--dm-text-soft: #77875b` - Soft text (olive 500)

### Accent Colors
- `--dm-accent: #77875b` - Olive 500 accent
- `--dm-accent-soft: #e6eadd` - Olive 100 soft accent
- `--dm-accent-dark: #3c4430` - Olive 800 primary action color
- `--dm-accent-dark-hover: #1a1f14` - Olive 950 hover color
- `--dm-accent-gold: #94a378` - Olive 400 highlight accent
- `--dm-link: #5c6a46` - Olive 600 link color
- `--dm-nav-bg: #1a1f14` - Olive 950 navigation background

### Status/State Colors
- **Pending**: `--dm-pending-bg: #fff4e8` | `--dm-pending-text: #a64b1f`
- **Confirmed**: `--dm-confirmed-bg: #e7f6ef` | `--dm-confirmed-text: #17684f`
- **Standby/Unassigned**: `--dm-standby-bg: #fff8dc` | `--dm-standby-text: #8a6416`
- **Danger/Cancelled**: `--dm-danger-bg: #fde8e2` | `--dm-danger-text: #a83524`
- **Neutral**: `--dm-neutral-bg: #edf2ea` | `--dm-neutral-text: #667368`

### Semantic Colors
- `--dm-info-text: #52525b` - Info text
- `--dm-info-strong: #27272a` - Strong info
- `--dm-lavender: #8b5cf6` - Lavender accent
- `--dm-success-strong: #16a34a` - Success (strong green)
- `--dm-danger-strong: #dc2626` - Danger (strong red)

### Primary Palette (Olive Green)
- `--dm-primary-50 to 950` - Range from #f4f5f0 (light) to #1a1f14 (dark)

### Border/Effects
- `--dm-border: rgba(17, 24, 39, 0.08)` - Light border
- `--dm-border-strong: rgba(17, 24, 39, 0.14)` - Stronger border
- `--dm-shadow-sm` / `--dm-shadow-md` - Drop shadows
- `--dm-focus-ring` - Focus outline

---

## 2. COMPONENT INVENTORY

### 2.1 Navigation Components

#### Navbar (Customer/Public Facing)
**Location**: [includes/header.php](includes/header.php) (Lines 50-100)
**Class**: `.navbar-modern`
- **Background**: `#111111` (black)
- **Border**: `1px solid rgba(255,255,255,0.06)` (subtle white border)
- **Shadow**: `0 10px 24px rgba(0, 0, 0, 0.22)` (dark shadow)
- **Text Color**: `rgba(255, 255, 255, 0.68)` (light text)
- **Active State**: `rgba(255, 255, 255, 0.12)` background with `#ffffff` text

**Child Elements**:
- `.logo` - White text, `#ffffff`, 22px, weight 700
- `.nav-links a` - Light text with hover effects
- `.btn-book` - White background, `#111111` text, distinct CTA button
- `.btn-logout` - Red-tinted button with `rgba(239, 68, 68, 0.12)` background, `#fca5a5` text

#### Admin Sidebar
**Location**: [admin/partials/admin-sidebar.php](admin/partials/admin-sidebar.php) (Lines 20-80)
**Class**: `.sidebar`
- **Background**: `var(--dm-accent-dark)` (`#161616`)
- **Width**: 96px (collapsed), 248px (expanded on hover)
- **Text**: `rgba(255, 255, 255, 0.84)` (light text)
- **Border**: `1px solid rgba(255,255,255,0.06)` (subtle white)
- **Hover**: Box-shadow `18px 0 32px rgba(10, 18, 34, 0.18)`

**Child Elements**:
- `.sidebar h4` - White text, `#ffffff`, 700 weight
- `.sidebar a` - Light text with border-radius and transition effects
- Icons (24px) centered with opacity transitions

#### Admin Topbar
**Location**: [admin/partials/admin-topbar.php](admin/partials/admin-topbar.php) (Lines 50-80)
**Class**: `.topbar`
- **Background**: `rgba(255, 255, 255, 0.94)` (semi-transparent white)
- **Height**: 74px
- **Border**: `1px solid var(--dm-border)` (light border)
- **Shadow**: `0 10px 26px rgba(15, 23, 42, 0.04)` (subtle shadow)
- **Backdrop Filter**: blur(10px)

---

### 2.2 Card Components

#### Generic Cards
**Classes**: `.dm-card`, `.dm-panel`, `.dm-surface`, `.dm-card-soft`
**Location**: [assets/css/app.css](assets/css/app.css) (Lines 250-280)

**Styling**:
- **Background**: `var(--dm-surface)` (#ffffff)
- **Border**: `1px solid var(--dm-border)` (light border)
- **Border Radius**: `var(--dm-radius-lg)` (12px)
- **Shadow**: `var(--dm-shadow-md)` or `var(--dm-shadow-sm)`

#### App Cards
**Classes**: `.app-card`, `.app-card-header`, `.app-card-body`
**Location**: [assets/css/app.css](assets/css/app.css) (Lines 1030-1060)

**Structure**:
- `.app-card` - Container with white background, border, border-radius 10px
- `.app-card-header` - Has `1px solid var(--dm-border)` bottom border
- `.app-card-body` - Padding 20px 24px

#### Feature Cards (About/Contact Pages)
**Location**: [public/about.php](public/about.php) (Lines 15-40)
- **Background**: `white`
- **Border**: `1px solid var(--dm-border)`
- **Border-radius**: `10px`
- **Padding**: `30px` or `40px`
- **Shadow**: `0 4px 16px rgba(15,23,42,0.06)` (base), `0 8px 24px rgba(15,23,42,0.10)` (hover)

#### Dashboard Panels
**Location**: [customer/dashboard.php](customer/dashboard.php) (Lines 25-40)
**Classes**: `.dashboard-hero`, `.dashboard-panel`
- **Background**: `var(--dm-surface)` (white)
- **Border**: `1px solid var(--dm-border)`
- **Border-radius**: `12px`
- **Shadow**: `0 4px 16px rgba(15, 23, 42, 0.06)`
- **Padding**: `28px`

#### Booking Stage Card
**Location**: [customer/book-table.php](customer/book-table.php)
**Class**: `.booking-stage`
- **Background**: `var(--dm-surface)` (white)
- **Border**: `1px solid var(--dm-border)`
- **Border-radius**: `12px`
- **Shadow**: `0 4px 16px rgba(15, 23, 42, 0.06)`
- **Padding**: `28px`

#### Menu Cards
**Location**: [public/index.php](public/index.php) and [public/menu.php](public/menu.php)
**Class**: `.menu-card`
- **Background**: `var(--dm-surface)` (white)
- **Border**: `1px solid var(--dm-border)`
- **Border-radius**: `var(--dm-radius-md)` (10px)
- **Shadow**: `0 4px 16px rgba(0,0,0,0.06)` (base), `0 8px 24px rgba(0,0,0,0.10)` (hover)
- **Image Height**: `260px` with `object-fit: cover`

---

### 2.3 Button Components

#### Primary Buttons
**Classes**: `.dm-button`, `.btn-primary`
**Location**: [assets/css/app.css](assets/css/app.css) (Lines 295-320)

**Styling**:
- **Background**: `var(--dm-accent-dark)` (#161616)
- **Color**: `#ffffff` (white text)
- **Border**: `1px solid var(--dm-accent-dark)`
- **Shadow**: `0 8px 18px rgba(0, 0, 0, 0.12)`
- **Padding**: `8px 14px`
- **Min-height**: `38px`
- **Border-radius**: `8px`
- **Hover**: Changes to `var(--dm-accent-dark-hover)` (#000000)

#### Secondary Buttons
**Classes**: `.dm-button-secondary`, `.btn-secondary`
**Location**: [assets/css/app.css](assets/css/app.css) (Lines 320-330)

**Styling**:
- **Background**: `var(--dm-surface)` (white)
- **Border**: `1px solid var(--dm-border-strong)` (light border)
- **Color**: `var(--dm-text)` (dark text)
- **Hover**: Background becomes `var(--dm-surface-muted)` (#f3f4f6)

#### Danger/Warning Buttons
**Classes**: `.dm-button-danger`, `.dm-button-warn`
**Location**: [assets/css/app.css](assets/css/app.css) (Lines 330-345)

**Danger**:
- **Background**: `var(--dm-danger-bg)` (#fee2e2 light red)
- **Text**: `var(--dm-danger-text)` (#b91c1c dark red)
- **Border**: `#fecaca`

**Warn**:
- **Background**: `var(--dm-standby-bg)` (#fefce8 light yellow)
- **Text**: `var(--dm-standby-text)` (#a16207 dark yellow)
- **Border**: `#fde68a`

#### Contact Button
**Location**: [public/contact.php](public/contact.php)
**Class**: `.btn-contact`
- **Background**: `var(--dm-accent-dark)` (#161616)
- **Border**: `1px solid var(--dm-accent-dark)`
- **Color**: `var(--dm-surface)` (white)
- **Padding**: `12px 25px`
- **Border-radius**: `8px`
- **Hover**: `var(--dm-accent-dark-hover)` (#000000)

---

### 2.4 Badge/Status Components

#### Status Badges
**Classes**: `.dm-status-badge`, `.status-tag`
**Location**: [assets/css/app.css](assets/css/app.css) (Lines 345-375)

**Status Variants**:
- `.pending` - Background: `#fff7ed` (orange), Text: `#c2410c`
- `.confirmed` / `.completed` - Background: `#ecfdf5` (green), Text: `#047857`
- `.cancelled` / `.danger` / `.conflict` - Background: `#fee2e2` (red), Text: `#b91c1c`
- `.standby` / `.unassigned` - Background: `#fefce8` (yellow), Text: `#a16207`
- `.neutral` - Background: `#f3f4f6` (gray), Text: `#6b7280`
- `.available` - Green
- `.unavailable` - Red

**General Styling**:
- **Padding**: `4px 10px` (badges), `2px 7px` (tags)
- **Border-radius**: `var(--dm-radius-xs)` (6px)
- **Font-size**: `12px`
- **Font-weight**: `600`
- **Borders**: 1px matching lighter shade of background color

---

### 2.5 Form Components

#### Form Inputs
**Location**: [assets/css/app.css](assets/css/app.css) (Lines 430-460)

**Styling**:
- **Elements**: `.form-control`, `.form-select`, `input[type='*']`, `select`, `textarea`
- **Border-radius**: `8px`
- **Border**: `1px solid var(--dm-border-strong)` (light border)
- **Background**: `var(--dm-surface)` (white)
- **Color**: `var(--dm-text)` (dark)
- **Padding**: `10px 12px`
- **Font-size**: `14px`
- **Focus State**:
  - **Border-color**: `var(--dm-accent-dark)` (dark)
  - **Box-shadow**: `var(--dm-focus-ring)` (light outline)
  - **Outline**: `none`

#### Form Labels
**Location**: [assets/css/app.css](assets/css/app.css) (Lines 420-430)

**Styling**:
- **Color**: `var(--dm-text)` (dark)
- **Font-size**: `14px`
- **Font-weight**: `600`
- **Margin-bottom**: `6px`

---

### 2.6 Table Components

**Location**: [assets/css/app.css](assets/css/app.css) (Lines 465-485)

**Classes**: `.table` (Bootstrap with customizations)

**Styling**:
- **Header** (`.table th`):
  - **Font-size**: `12px`
  - **Text-transform**: `uppercase`
  - **Color**: `var(--dm-text-soft)` (light gray)
  - **Font-weight**: `600`
  - **Border-bottom-color**: `var(--dm-border)`

- **Cells** (`.table td`):
  - **Color**: `var(--dm-text)` (dark)
  - **Font-size**: `14px`
  - **Border-bottom-color**: `var(--dm-border)`

- **Hover** (`.table tbody tr:hover`):
  - **Background**: `rgba(17, 24, 39, 0.022)` (subtle dark overlay)

---

### 2.7 Modal Components

**Location**: [assets/css/app.css](assets/css/app.css) (Lines 495-510)

**Classes**: `.modal-content`, `.modal-header`, `.modal-footer`

**Styling**:
- **Border-radius**: `12px`
- **Border**: `1px solid var(--dm-border)`
- **Shadow**: `0 24px 56px rgba(0, 0, 0, 0.10)`
- **Header/Footer Border**: `1px solid var(--dm-border)`

#### Alert Component
**Classes**: `.alert`
- **Border-radius**: `8px`
- **Border**: `1px solid var(--dm-border)`

---

### 2.8 Page Section Components

#### Hero Sections
**Location**: [public/index.php](public/index.php), [public/about.php](public/about.php), [public/contact.php](public/contact.php), [auth/login.php](auth/login.php)

**Classes**: `.hero`, `.about-hero`, `.contact-hero`

**Styling**:
- **Height**: `100vh` (hero) or `300px`/`260px` (about/contact)
- **Background**: URL image with dark overlay
- **Overlay Gradient**: `linear-gradient(rgba(18,32,51,0.76),rgba(18,32,51,0.42))` or similar (dark)
- **Text Color**: `#ffffff` (white)
- **Text Shadow**: `0 10px 28px rgba(0, 0, 0, 0.32)` (h1/h2/h3), `0 8px 22px rgba(0, 0, 0, 0.24)` (p)
- **Display**: `flex` with centering

**Headings in Hero**:
- **Color**: `#ffffff` (white)
- **Font-size**: `60px` (hero), `32px` (mobile)
- **Font-weight**: `700`

#### Booking Progress
**Location**: [customer/book-table.php](customer/book-table.php)
**Class**: `.booking-progress`
- **Display**: `flex` with centering
- **Related Classes**: `.booking-topbar`, `.booking-heading`

#### Page Shell/Container
**Location**: [assets/css/app.css](assets/css/app.css) (Lines 220-240)
**Classes**: `.dm-page-shell`, `.dm-page-bg`

**Styling**:
- **Max-width**: `1320px`
- **Margin**: `0 auto`
- **Padding**: `24px` (desktop), `16px` (mobile)
- **Background**: `var(--dm-bg)` (#f8f8f7)

#### Site Sections
**Location**: [assets/css/app.css](assets/css/app.css) (Lines 590-610)
**Classes**: `.site-section`, `.site-card`

**Styling**:
- **Padding**: `88px 0`
- **Card Background**: `var(--dm-surface)` (white)
- **Card Border**: `1px solid var(--dm-border)`
- **Card Border-radius**: `12px`
- **Card Shadow**: `var(--dm-shadow-md)`

---

### 2.9 Footer Component

**Location**: [includes/footer.php](includes/footer.php) (Lines 45-85)
**Class**: `.footer`

**Styling**:
- **Background**: `var(--dm-surface)` (white)
- **Border-top**: `1px solid var(--dm-border)`
- **Margin-top**: `72px`
- **Padding**: `56px 0 0`

**Child Elements**:
- `.footer h4`, `.footer h5` - Color: `var(--dm-text)`, weight `700`
- `.footer p`, `.footer ul li a` - Color: `var(--dm-text-muted)` (#5f6368)
- `.social-icons a`:
  - **Width/Height**: `36px` circle
  - **Background**: `var(--dm-surface-muted)` (#f3f4f6)
  - **Color**: `var(--dm-text)` (dark)
  - **Hover**: Background becomes `#111111` (black), Color becomes `#ffffff` (white)
- `.back-top` button:
  - **Border**: `1px solid var(--dm-border-strong)`
  - **Background**: `var(--dm-surface)` (white)
  - **Color**: `var(--dm-text)` (dark)
  - **Hover**: Background becomes `var(--dm-surface-muted)`

---

### 2.10 Typography/Text Components

**Location**: [assets/css/app.css](assets/css/app.css) (Lines 150-210)

#### Headings
- **h1**: 28px, weight 700, color `var(--dm-text)`
- **h2, .dm-section-title**: 20px, weight 600
- **h3, .dm-card-title**: 16px, weight 600
- **All Headings**: Letter-spacing `-0.02em`

#### Body Text Classes
- `.dm-label` - 12px, uppercase, weight 600, color `var(--dm-text-soft)`
- `.dm-body-text` - 14px, weight 400, color `var(--dm-text-muted)`
- `.dm-muted` - Color `var(--dm-text-muted)`

---

### 2.11 Utility Classes

**Location**: [assets/css/app.css](assets/css/app.css) (Lines 75-145)

**Spacing/Layout Utilities**:
- `.dm-flex`, `.dm-inline-flex` - Display flex
- `.dm-flex-wrap` - Flex wrapping
- `.dm-items-center`, `.dm-justify-center` - Alignment
- `.dm-gap-8`, `.dm-gap-10`, `.dm-gap-12` - Gap spacing
- `.dm-mt-*`, `.dm-mb-*`, `.dm-ml-*` - Margin utilities
- `.dm-w-full`, `.dm-max-w-full` - Width utilities
- `.dm-text-*` - Font size utilities
- `.dm-border-0`, `.dm-no-underline`, `.dm-text-center` - Misc utilities

---

### 2.12 Admin-Specific Components

#### Admin Layout
**Location**: [assets/css/app.css](assets/css/app.css) (Lines 705-730)
**Classes**: `.admin-layout`, `.main-content`, `.admin-container`

**Styling**:
- **Layout**: `display: flex` with sidebar + content
- **Min-height**: `100vh`
- **Container Padding**: `24px` (desktop), `16px` (mobile)
- **Background**: Inherits page background

#### Admin Stats Strip
**Location**: [assets/css/app.css](assets/css/app.css) (Lines 865-920)
**Classes**: `.app-stats-strip`, `.app-stat-cell`

**Styling**:
- **Background**: `var(--dm-surface)` (white)
- **Border**: `1px solid var(--dm-border)`
- **Border-radius**: `10px`
- **Cell Borders**: Right border `1px solid var(--dm-border)` (removed on last)
- **Label Color**: `var(--dm-text-soft)` (light gray)
- **Value Color**: `var(--dm-text)` (dark)
- **Note Color**: `var(--dm-text-muted)` (medium gray)

#### Admin Page Header
**Location**: [assets/css/app.css](assets/css/app.css) (Lines 825-850)
**Classes**: `.app-page-header`

**Styling**:
- **Display**: `flex` with space-between
- **h1 Color**: `var(--dm-text)`, font-size `20px`, weight `700`
- **p Color**: `var(--dm-text-muted)`, font-size `13px`

#### Analytics Components
**Location**: [admin/pages/analytics.php](admin/pages/analytics.php)
**Classes**: `.analytics-topbar-controls`, `.analytics-range-chip`, `.analytics-range-group`

**Styling**:
- **Chip (Inactive)**: Light background with dark text
- **Chip (Active)**: Dark background with light text (`.is-active`)

#### Timeline Components
**Location**: [admin/timeline/timeline.php](admin/timeline/timeline.php)
**Classes**: `.timeline-panel-tools`, `.timeline-date-card`

**Styling**:
- **Background**: `var(--dm-surface)` (white)
- **Border**: `1px solid var(--dm-border)`
- **Border-radius**: `10px`
- **Box-shadow**: `0 4px 12px rgba(15, 23, 42, 0.04)`

---

### 2.13 Authentication Pages

#### Auth Layout
**Location**: [auth/login.php](auth/login.php), [auth/register.php](auth/register.php)
**Classes**: `.auth-wrapper`, `.auth-box`, `.auth-left`, `.auth-right`

**Styling**:
- **Wrapper**: `min-height: 100vh`, centered flex layout
- **Box**: `max-width: 1080px` (login) or `1100px` (register)
  - **Background**: `var(--dm-surface)` (white)
  - **Border**: `1px solid var(--dm-border)`
  - **Border-radius**: `var(--dm-radius-lg)` (12px)
  - **Shadow**: `var(--dm-shadow-md)`
  - **Display**: `flex` with two columns
- **Left** (form): `flex: 1`, `padding: 56px` (login) or `48px 52px` (register)
- **Right** (image): `flex: 1`, background image with overlay
  - **Overlay**: `linear-gradient(180deg, rgba(22, 32, 51, 0.22), rgba(22, 32, 51, 0.62))`

#### Auth Right Content
**Class**: `.auth-right-content`
- **Color**: `#ffffff` (white)
- **Position**: Absolute bottom 40px, left 40px
- **Text Shadow**: Applies to h1-h6 and p elements

---

## 3. INLINE/PAGE-SPECIFIC STYLES SUMMARY

### Hero Section Gradients
- **Public Index**: `linear-gradient(120deg,rgba(18,32,51,0.76),rgba(18,32,51,0.42))`
- **About Hero**: `linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6))`
- **Contact Hero**: `linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6))`
- **Auth Pages**: `linear-gradient(180deg, rgba(22, 32, 51, 0.22), rgba(22, 32, 51, 0.62))`

### Custom Colors (Non-CSS Variables)
- **Feature Icon** (about.php): `#f4b400` (gold/yellow)
- **Contact Icon**: `#f4b400` (gold/yellow)
- **Menu Section Title Border**: `var(--dm-accent-dark)` with `::after` pseudo-element
- **Links**: `#3d6bdf` (blue, register.php)
- **Strength Bar** (register.php):
  - Danger: `var(--dm-danger-strong)` (#dc2626)
  - Warning: `#f59e0b` (orange)
  - Success: `var(--dm-success-strong)` (#16a34a)

---

## 4. HARDCODED COLORS BY FILE

### [admin/partials/admin-sidebar.php](admin/partials/admin-sidebar.php)
- Background: `var(--dm-accent-dark)` (#161616)
- Text: `rgba(255, 255, 255, 0.84)` (light)
- Border: `rgba(255,255,255,0.06)`

### [admin/partials/admin-topbar.php](admin/partials/admin-topbar.php)
- Background: `rgba(255, 255, 255, 0.94)` (semi-transparent white)
- Text: `var(--dm-text)` (dark)
- Border: `var(--dm-border)`

### [includes/header.php](includes/header.php)
- Navbar background: `#111111` (black)
- Text: `rgba(255, 255, 255, 0.68)` (light)
- Border: `rgba(255, 255, 255, 0.06)`
- Book button: White with dark text
- Logout button: Red-tinted

### [includes/footer.php](includes/footer.php)
- Background: `var(--dm-surface)` (white)
- Text: `var(--dm-text-muted)` (gray)
- Social icons hover: Black background with white text
- Border-top: `var(--dm-border)`

### [public/index.php](public/index.php)
- Hero overlay: `linear-gradient(120deg,rgba(18,32,51,0.76),rgba(18,32,51,0.42))`
- Button: `var(--dm-accent-dark)` with hover opacity change

### [public/about.php](public/about.php)
- Hero overlay: `linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6))`
- Feature icon: `#f4b400` (gold)
- Card hover shadow increase

### [public/contact.php](public/contact.php)
- Hero overlay: `linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6))`
- Contact icon: `#f4b400` (gold)
- Button: `var(--dm-accent-dark)`

### [public/menu.php](public/menu.php)
- Section title underline: `var(--dm-accent-dark)` with `::after` pseudo-element
- Container: Centered with max-width 1200px

### [customer/book-table.php](customer/book-table.php)
- Stage background: `var(--dm-surface)` (white)
- Shadow: `0 4px 16px rgba(15, 23, 42, 0.06)`

### [customer/dashboard.php](customer/dashboard.php)
- Panel background: `var(--dm-surface)` (white)
- Hero: Two-column grid with stretch alignment

### [customer/my-bookings.php](customer/my-bookings.php)
- Wrapper margin-top: `118px`, margin-bottom: `84px`

### [auth/login.php](auth/login.php)
- Auth box: White with border and shadow
- Form controls: Dark borders on focus
- Auth right overlay: `linear-gradient(180deg, rgba(22, 32, 51, 0.22), rgba(22, 32, 51, 0.62))`

### [auth/register.php](auth/register.php)
- Auth box: White with border and shadow
- Form controls: Similar to login
- Toast box: `var(--dm-accent-dark)` background, white text
- Success overlay: Dark with blur filter
- Password strength bars: Color-coded (red/warning/green)

### [admin/timeline/timeline.php](admin/timeline/timeline.php)
- Background: `var(--dm-surface-muted)` (#f3f4f6)
- Panel: `var(--dm-surface)` with subtle shadow

### [admin/pages/analytics.php](admin/pages/analytics.php)
- Range chip (inactive): Light background
- Range chip (active): Dark background with `.is-active` class

---

## 5. COLOR USAGE PATTERNS & OBSERVATIONS

### Primary Color Scheme
- **Olive 800** (`#3c4430`) is used for primary buttons, admin sidebar, and active states
- **Olive 950** (`#1a1f14`) is used for the public navbar and deepest text
- **Olive 400/500** (`#94a378`, `#77875b`) are used for highlights and softer accents
- **White** (`#ffffff`) is dominant for cards and content surfaces

### Status/State Color Strategy
- **Warm colors** (orange/yellow) for pending/standby states
- **Cool colors** (green) for confirmed/completed states
- **Red** for danger/cancelled states
- **Gray** for neutral states

### Text Color Hierarchy
- **Primary**: `#1a1f14` (olive 950) for main content
- **Secondary**: `#515d3f` (olive 700) for supporting text
- **Tertiary**: `#77875b` (olive 500) for labels
- **Inverted**: White for dark backgrounds

### Border Strategy
- **Light borders**: `rgba(17, 24, 39, 0.08)` for most elements
- **Stronger borders**: `rgba(17, 24, 39, 0.14)` for form inputs
- **White borders**: Subtle on dark backgrounds

### Shadow Strategy
- **Small shadows**: `0 6px 18px rgba(0, 0, 0, 0.035)` for subtle depth
- **Medium shadows**: `0 12px 32px rgba(0, 0, 0, 0.07)` for cards
- **Large shadows**: `0 24px 56px rgba(0, 0, 0, 0.10)` for modals

### Hover/Interactive Effects
- **Opacity changes**: Buttons often use opacity on hover
- **Background shifts**: Secondary buttons lighten on hover
- **Shadow increases**: Cards often increase shadow on hover
- **Color transitions**: All color/shadow changes use `0.18s ease` or similar

---

## 6. HARDCODED VS VARIABLE COLORS

### Good Usage (CSS Variables)
✅ Background colors (dm-surface, dm-bg, dm-surface-muted)
✅ Text colors (dm-text, dm-text-muted, dm-text-soft)
✅ Status colors (pending, confirmed, danger)
✅ Accent colors (dm-accent-dark, dm-accent-dark-hover)
✅ Borders (dm-border, dm-border-strong)

### Hardcoded Colors (Could Be Tokenized)
✅ Inverted text now uses `--dm-white` in shared and key page styles
✅ Dark action/nav colors now use green tokens instead of scattered black values
✅ Highlight accents now use `--dm-accent-gold` in public and booking flows
⚠️ Specific overlay gradients: Various linear gradients with hardcoded rgba values
⚠️ Link color: `#3d6bdf` (register.php - blue)
⚠️ Password strength colors: Multiple hardcoded values

---

## 7. COMPONENT LOCATIONS QUICK REFERENCE

| Component | File(s) | Classes | Key Color Variable |
|-----------|---------|---------|-------------------|
| **Navbar** | [includes/header.php](includes/header.php) | `.navbar-modern` | #111111 (black) |
| **Sidebar** | [admin/partials/admin-sidebar.php](admin/partials/admin-sidebar.php) | `.sidebar` | `--dm-accent-dark` |
| **Topbar** | [admin/partials/admin-topbar.php](admin/partials/admin-topbar.php) | `.topbar` | rgba(255,255,255,0.94) |
| **Cards** | [assets/css/app.css](assets/css/app.css) | `.dm-card`, `.app-card` | `--dm-surface` |
| **Buttons** | [assets/css/app.css](assets/css/app.css) | `.dm-button`, `.btn-*` | `--dm-accent-dark` |
| **Status Badges** | [assets/css/app.css](assets/css/app.css) | `.status-tag.*` | Status variables |
| **Forms** | [assets/css/app.css](assets/css/app.css) | `.form-control` | `--dm-border-strong` |
| **Tables** | [assets/css/app.css](assets/css/app.css) | `.table` | `--dm-border` |
| **Modals** | [assets/css/app.css](assets/css/app.css) | `.modal-content` | `--dm-border` |
| **Footer** | [includes/footer.php](includes/footer.php) | `.footer` | `--dm-surface` |
| **Hero Sections** | [public/index.php](public/index.php), etc. | `.hero`, `.about-hero` | Various gradients |
| **Auth Pages** | [auth/login.php](auth/login.php), [auth/register.php](auth/register.php) | `.auth-box` | `--dm-surface` |
| **Admin Layout** | [assets/css/app.css](assets/css/app.css) | `.admin-layout` | `--dm-surface` |
| **Stats Strip** | [assets/css/app.css](assets/css/app.css) | `.app-stats-strip` | `--dm-surface` |
| **Timeline** | [admin/timeline/timeline.php](admin/timeline/timeline.php) | `.timeline-*` | `--dm-surface` |

---

## 8. RECOMMENDATIONS FOR COLOR SYSTEM IMPROVEMENTS

1. **Tokenize hardcoded colors**:
   - Create `--dm-white: #ffffff` and `--dm-black: #111111` for inverted contexts
   - Create `--dm-link: #3d6bdf` for links
   - Create `--dm-accent-gold: #f4b400` for feature icons

2. **Standardize dark color usage**:
   - Currently using `#111111`, `#161616`, and `#000000` for different "dark" contexts
   - Consider a palette: `--dm-dark-100`, `--dm-dark-200`, `--dm-dark-300`

3. **Document overlay gradients**:
   - Create tokens for common overlay gradients (hero, auth, etc.)
   - Example: `--dm-gradient-dark-hero`, `--dm-gradient-dark-auth`

4. **Gradient variables**:
   - Replace hardcoded linear-gradients with CSS variables for easy theming

5. **Consistent shadow depth**:
   - Already good - keep using `--dm-shadow-sm` and `--dm-shadow-md`

6. **Focus state standardization**:
   - `--dm-focus-ring` is good - ensure consistent usage across all interactive elements

---

## 9. SUMMARY STATISTICS

- **Total CSS Variable Tokens**: 50+ (colors, shadows, radius, spacing)
- **Main Color Groups**: 5 (Base, Text, Accent, Status, Primary Palette)
- **Component Types**: 13+ (Nav, Sidebar, Cards, Buttons, Badges, Forms, Tables, Modals, Sections, Footer, Auth, Admin, Timeline)
- **Files with Styling**: 25+ (PHP and CSS files)
- **Hardcoded Colors to Consider Tokenizing**: ~8-10
- **Responsive Breakpoints**: 768px (primary), 991px (secondary)
- **Primary Shadows**: 2 main variants (small, medium) + modals

---

**Audit Date**: April 2026  
**DineMate Version**: As of current workspace state  
**Framework**: Plain PHP + MySQL with Bootstrap 5.3.2 + custom CSS variables
