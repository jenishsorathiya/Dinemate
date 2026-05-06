# DineMate Landing - Quick Start Guide

## 🚀 Quick Access

Access the landing site at:
```
http://localhost/Dinemate/Dinemate-Landing/
```

## 📄 Page Map

| Page | URL | Purpose |
|------|-----|---------|
| Homepage | `/index.php` | Main entry point with search & features |
| For Diners | `/pages/for-diners.php` | Diner-focused features & benefits |
| For Restaurants | `/pages/for-restaurants.php` | Restaurant management features |
| Pricing | `/pages/pricing.php` | Pricing plans & comparison |
| About | `/pages/about.php` | Company info & milestones |
| Contact | `/pages/contact.php` | Contact form & support |

## 🎨 Customization Quick Tips

### Change Brand Colors
Edit `/assets/css/global.css` line 8-24:
```css
:root {
    --primary-color: #2E7D32;        /* Change this */
    --primary-light: #4A7C59;         /* And this */
    --primary-dark: #1B5E20;          /* And this */
    /* ... etc ... */
}
```

### Update Logo/Brand Name
Edit `/includes/header.php` around line 38:
```php
<span class="logo-text">DineMate</span>
```

### Modify Navigation Links
Edit `/includes/header.php` (lines 45-55) to add/remove nav items.

### Update Social Links
Edit `/includes/footer.php` (lines 29-34) to add social media URLs.

## 🔧 Common Edits

### Add a New Feature Card
Find a card section and duplicate:
```php
<div class="card">
    <div class="card-icon"><i class="fas fa-star"></i></div>
    <h3>Feature Title</h3>
    <p>Feature description here.</p>
</div>
```

### Add a Testimonial
Find testimonial section and add:
```php
<div class="testimonial-card">
    <div class="testimonial-header">
        <div class="testimonial-avatar">JD</div>
        <div>
            <h4>John Doe</h4>
            <div class="testimonial-role">Role/Title</div>
        </div>
    </div>
    <div class="testimonial-stars">★★★★★</div>
    <p class="testimonial-text">"Quote here"</p>
</div>
```

## 📱 Responsive Testing

Test on different devices:
```
Desktop:  1400px+
Tablet:   768px - 1399px
Mobile:   480px - 767px
Small:    < 480px
```

Use browser dev tools (F12) to test responsive design.

## 🎯 Current Features

✅ Modern SaaS-style design  
✅ Mobile responsive  
✅ Smooth animations & interactions  
✅ Multiple landing page variations  
✅ Testimonials & social proof  
✅ Pricing tables & comparisons  
✅ Contact forms & FAQs  
✅ Footer with links  
✅ Fast load times  

## ⚠️ Known Limitations

- Forms currently show alerts (not connected to backend)
- No database integration
- Images are placeholder emojis
- Some buttons are demo placeholders

## 🔐 Security Notes

- No sensitive data stored
- All forms need backend validation
- CSRF protection needed for forms
- SQL injection prevention when connecting DB
- Input sanitization required

## 📚 File Reference

```
Dinemate-Landing/
│
├── 📄 index.php                    Main homepage
├── 📄 README.md                    Full documentation
├── 📄 QUICKSTART.md                This file
│
├── 📁 assets/
│   ├── 📁 css/
│   │   └── global.css              All styles (1000+ lines)
│   └── 📁 js/
│       └── main.js                 Interactive features
│
├── 📁 includes/
│   ├── header.php                  Navigation bar
│   └── footer.php                  Footer component
│
└── 📁 pages/
    ├── for-diners.php              Diner features
    ├── for-restaurants.php         Restaurant features
    ├── pricing.php                 Pricing info
    ├── about.php                   About company
    └── contact.php                 Contact form
```

## 🎯 Next Steps

1. **Test all pages** - Click through and verify links
2. **Check mobile** - Test on phone/tablet
3. **Customize colors** - Update brand colors
4. **Add real images** - Replace emoji placeholders
5. **Connect forms** - Wire up contact form
6. **Add analytics** - Implement Google Analytics
7. **Setup DNS** - Point domain to landing page
8. **Setup SSL** - Enable HTTPS

## 💬 Template Variables

The site uses PHP variables for page-specific content:
```php
$pageTitle = "Page Title";          // Browser tab
$pageDescription = "Meta description...";  // SEO
```

## 🎨 CSS Classes You Can Use

```
.container              Max width container
.container-sm           Smaller max width
.section                Section wrapper
.section-dark           Dark background section
.hero                   Hero banner
.card                   Card component
.btn                    Button base
.btn-primary            Primary button
.btn-secondary          Secondary button
.pricing-card           Pricing card
.testimonial-card       Testimonial card
.feature-row            Feature section
```

## 📊 Responsive Breakpoints

```css
@media (max-width: 768px) { /* Tablet */ }
@media (max-width: 480px) { /* Mobile */ }
```

## 🚨 Troubleshooting

**Page not loading?**
- Check file path in browser
- Verify PHP is enabled
- Check file permissions

**Styles not applying?**
- Clear browser cache (Ctrl+Shift+Delete)
- Check CSS file path in header.php
- Verify assets/css/global.css exists

**Images not showing?**
- Replace emoji with real image src
- Check image path is correct
- Verify image file exists

**Mobile menu not working?**
- Check main.js is loaded
- Verify JavaScript enabled in browser
- Check browser console for errors

## 📞 Support

- Check README.md for full documentation
- Review CSS comments for styling guidance
- HTML is well-structured and commented

---

**Created**: May 2026  
**Status**: Production Ready  
**License**: For DineMate use only
