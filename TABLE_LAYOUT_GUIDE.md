<!-- Restaurant Table Layout Guide -->
<!-- This document explains the new restaurant table visualization in book-table.php -->

# DineMate Table Selection - Visual Layout Guide

## 📋 Overview
The booking form now displays tables in a realistic restaurant floor plan layout with:
- ✅ Left side tables (Tables 1-3)
- ✅ Middle/Center tables (Tables 4-6) 
- ✅ Right side tables (Tables 7+)

## 🎨 Visual Features

### Table Cards Include:
1. **Table Icon** - Changes based on capacity:
   - 👥 = 2-seater (couples)
   - 🪑 = 4-seater (small groups)
   - 🍽️ = 6-seater (medium groups)
   - 🛋️ = 8-seater (larger groups)
   - 🏛️ = 10+ seaters (large parties)

2. **Table Number** - Clearly shows which table

3. **Capacity Badge** - Shows how many guests the table seats

4. **Status Badge** - Color-coded:
   - 🟢 **Green "Available"** - Can select this table
   - 🔴 **Red "Already Booked"** - Shows conflicting time (e.g., "Booked: 2:30 PM - 3:30 PM")

### Status Colors:
- **White/Available** - Light background, green badge, cursor changes to pointer
- **Golden/Selected** - Yellow highlight, scale animation, "Available" badge
- **Red/Booked** - Light red background, red badge, greyed out, cannot select

## 🏪 Restaurant Floor Plan Layout

```
┌─────────────────────────────────────────────────────┐
│                  Restaurant Floor Plan               │
├─────────────────────────────────────────────────────┤
│                                                     │
│  LEFT SIDE      │     CENTER TABLES      │  RIGHT   │
│   (Tables 1-3)  │     (Tables 4-6)       │  SIDE    │
│                 │                         │ (7+)     │
│  ┌───────────┐  │  ┌──────┐ ┌──────┐   │ ┌─────┐  │
│  │ Table 1   │  │  │Table4│ │Table5│   │ │Table│  │
│  │ 👥 Cap: 2 │  │  │🍽️ 6 │ │🍽️ 6 │   │ │ 7   │  │
│  │ Available │  │  │Avail │ │Avail │   │ │🛋️ 8 │  │
│  └───────────┘  │  └──────┘ └──────┘   │ │Avail│  │
│                 │                       │ └─────┘  │
│  ┌───────────┐  │  ┌──────┐            │          │
│  │ Table 2   │  │  │Table6│            │ ┌─────┐  │
│  │🪑 Cap: 4  │  │  │🪑 4  │            │ │Table│  │
│  │ Available │  │  │ Booked            │ │ 8   │  │
│  └───────────┘  │  │ (3:00-4:00)        │ │🛋️ 10│  │
│                 │  └──────┘             │ │Avail│  │
│  ┌───────────┐  │                       │ └─────┘  │
│  │ Table 3   │  │                       │          │
│  │🪑 Cap: 4  │  │                       │          │
│  │ Available │  │                       │          │
│  └───────────┘  │                       │          │
│                 │                       │          │
└─────────────────────────────────────────────────────┘
```

## 🎯 Features

### Real-Time Availability Checking
When you select a date and time:
1. ✅ The form automatically checks availability for all tables
2. ✅ Available tables show green "Available" badge
3. ✅ Booked tables show red "Already Booked: X:XX AM - Y:YY PM"
4. ✅ Counter updates: "X available • Y already booked"

### Interactive Table Selection
- 🖱️ **Hover**: Cards lift up and glow (non-booked tables only)
- 🖱️ **Click**: Table gets selected with golden highlight
- 🚫 **Booked**: Greyed out, cannot click, shows conflict time
- ✅ **Selected**: Golden border, scaled up, shows in form field

### Responsive Design
- **Desktop (1200px+)**: Full 5-column layout (left 1 | center 3 | right 1)
- **Tablet (768px+)**: 4-column layout (left 1 | center 2 | right 1)
- **Mobile (<768px)**: Stacked layout (left | center | right)

## 💡 User Experience

### Step-by-Step Booking:
```
1. Select Date → Calendar picker
2. Enter Start Time → Time input (e.g., 2:00 PM)
3. Enter End Time → Time input (e.g., 3:00 PM)
4. Form auto-checks availability
   ↓
5. Tables update in real-time
   - Available tables: Can click
   - Booked tables: Greyed out with time
6. Click available table
   ↓
7. Table highlights in gold
8. Number of guests → Dropdown
9. Special requests → Text area
10. Click "Confirm Booking"
    ↓
11. Backend double-checks for conflicts
12. If no conflicts: Booking confirmed!
    If conflict: Error message with options
```

## 🎨 CSS Classes Used

| Class | Purpose |
|-------|---------|
| `.restaurant-layout` | Main floor plan grid |
| `.table-section-left` | Left side table column |
| `.table-section-middle` | Center tables grid |
| `.table-section-right` | Right side table column |
| `.table-card` | Individual table card |
| `.table-card.selected` | Currently selected table |
| `.table-card.booked` | Unavailable/booked table |
| `.table-visual` | Table icon/emoji |
| `.table-number` | Table name |
| `.table-capacity` | Seating capacity |
| `.table-status` | Status badge container |

## 📱 Mobile Consideration

On mobile devices:
- Tables stack vertically for easier scrolling
- Touch-friendly tap targets (160px minimum)
- Larger spacing for easier selection
- Status badges clearly visible

## 🔧 Customization

To modify the layout:

### Change table organization:
Edit the logic in `book-table.php` around line 270:
```php
if ($table['table_number'] <= 3) {
    $leftTables[] = $table;      // Change 3 to your max left table
} elseif ($table['table_number'] > 6) {
    $rightTables[] = $table;     // Change 6 to your threshold
} else {
    $middleTables[] = $table;
}
```

### Change table icons:
Edit the `$icon = match()` statement around line 290:
```php
$icon = match($table['capacity']) {
    2 => '👥',      // Change emojis here
    4 => '🪑',
    6 => '🍽️',
    8 => '🛋️',
    10, 15 => '🏛️',
    default => '🪑'
};
```

### Adjust layout grid:
Edit CSS `.restaurant-layout` around line 60:
```css
.restaurant-layout {
    grid-template-columns: repeat(5, 1fr);  /* Change 5 to adjust */
}
```

---

## ✨ Benefits

✅ **Visual Appeal** - More engaging than plain text lists
✅ **Intuitive** - Mirrors actual restaurant floor plan
✅ **Real-time Feedback** - See available tables immediately
✅ **Clear Status** - Knows which tables are booked and when
✅ **Mobile Friendly** - Responsive across all devices
✅ **Accessible** - Keyboard navigation supported
