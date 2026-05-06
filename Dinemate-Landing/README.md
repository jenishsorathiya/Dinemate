# DineMate Landing Website

A modern, professional SaaS-style landing website for DineMate - a restaurant reservation platform inspired by OpenTable.

## 📁 Project Structure

```
Dinemate-Landing/
├── assets/
│   ├── css/
│   │   └── global.css          # Global styles and design system
│   └── js/
│       └── main.js              # Interactive features and animations
├── includes/
│   ├── header.php               # Navigation header component
│   └── footer.php               # Footer component
├── pages/
│   ├── about.php                # About DineMate
│   ├── contact.php              # Contact form and support info
│   ├── for-diners.php           # Features for end users
│   ├── for-restaurants.php      # Features for restaurant partners
│   └── pricing.php              # Pricing plans for both segments
└── index.php                    # Homepage
```

## 🎯 Pages Overview

### Homepage (`index.php`)
- Hero section with search functionality
- Feature highlights
- How it works guide
- Customer testimonials
- Call-to-action sections

### For Diners (`pages/for-diners.php`)
- Diner-focused features and benefits
- Real-time reservations
- Rewards program information
- Success stories from users

### For Restaurants (`pages/for-restaurants.php`)
- Restaurant management features
- Growth and revenue opportunities
- Analytics and insights
- Pricing plans for restaurants
- Success stories from restaurant partners

### Pricing (`pages/pricing.php`)
- Dual pricing model (Diners & Restaurants)
- Feature comparison table
- FAQ section
- Flexible payment options

### About (`pages/about.php`)
- Company mission and values
- Leadership team
- Company milestones and timeline
- Impact statistics
- Career opportunities

### Contact (`pages/contact.php`)
- Contact form
- Multiple support channels
- FAQ section
- Response time information

## 🎨 Design System

### Color Palette
- **Primary**: `#2E7D32` (Green)
- **Primary Light**: `#4A7C59`
- **Primary Dark**: `#1B5E20`
- **Secondary**: `#1F2937` (Dark Gray)
- **Accent**: `#E74C3C` (Red)
- **Text Primary**: `#111827`
- **Text Secondary**: `#6B7280`
- **Background Light**: `#F9FAFB`

### Typography
- **Font Family**: System fonts (SF Pro, Segoe UI, Roboto)
- **Headings**: Bold (700-800 weight)
- **Body**: Regular (400-500 weight)
- **Responsive**: Uses `clamp()` for fluid typography

### Components
- Cards with hover effects
- Feature rows (alternating layout)
- Testimonial cards
- Pricing tables
- Navigation with mobile toggle
- CTA sections
- Footer with links

## 📱 Responsive Design

The website is fully responsive with breakpoints:
- **Desktop**: Full layout
- **Tablet**: 768px and below
- **Mobile**: 480px and below

Mobile features:
- Hamburger navigation menu
- Touch-friendly buttons
- Optimized grid layouts
- Single-column content

## ✨ Features

### Interactive Elements
- Smooth scroll navigation
- Mobile menu toggle
- Fade-in animations on scroll
- Hover effects on cards and buttons
- Form input focus states

### SEO & Performance
- Clean semantic HTML
- Meta tags for page descriptions
- Font Awesome icons (6.4.0)
- Lightweight CSS
- Lazy-loaded content ideas

## 🚀 Getting Started

1. Place the `Dinemate-Landing` folder in your web root
2. Access via: `http://localhost/Dinemate/Dinemate-Landing/`
3. All pages are self-contained and work independently

## 🔗 Navigation Structure

The navigation flows between pages while maintaining a consistent header and footer across all pages.

### Main Routes
- `/index.php` - Homepage
- `/pages/for-diners.php` - Consumer features
- `/pages/for-restaurants.php` - Restaurant management
- `/pages/pricing.php` - Pricing information
- `/pages/about.php` - Company information
- `/pages/contact.php` - Contact & support

## 💡 Design Inspiration

This landing website takes inspiration from modern SaaS platforms like OpenTable while maintaining a unique identity for DineMate. It focuses on:

- **User-first design**: Clean, intuitive interface
- **Trust signals**: Testimonials, statistics, transparency
- **Dual audience**: Separate sections for diners and restaurants
- **Modern aesthetics**: Gradients, cards, smooth interactions
- **Accessibility**: Semantic HTML, sufficient color contrast

## 🔧 Customization

### Updating Colors
Edit CSS variables in `assets/css/global.css`:
```css
:root {
    --primary-color: #2E7D32;
    /* ... other variables ... */
}
```

### Adding Content
- Edit PHP files directly
- Update text, images, and sections
- Maintain consistent structure with existing sections

### Adding Features
- Extend `assets/js/main.js` for interactivity
- Add new CSS classes to `assets/css/global.css`
- Keep responsive design in mind

## 📧 Contact & Support Information

The contact page includes:
- Support email: `support@dinemate.com`
- Partnerships: `partners@dinemate.com`
- Business: `business@dinemate.com`
- 24/7 support availability

## 📝 Notes

- This is a standalone marketing website separate from the main application
- No database integration required (static content)
- All forms currently show alerts (ready for backend integration)
- Icons from Font Awesome Pro (fallback to free tier)

## 🎯 Next Steps

To fully integrate:
1. Connect contact forms to backend email service
2. Add dynamic content from database
3. Implement analytics tracking
4. Set up SSL certificates
5. Configure email notifications
6. Add blog/news section

---

**Version**: 1.0  
**Last Updated**: May 2026  
**Status**: Ready for demo and research
