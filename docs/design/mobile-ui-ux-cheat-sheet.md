# Mobile UI/UX Design Cheat Sheet

## 📱 Mobile UI/UX Measurements

| Element                     | Recommended size / rule                           |
| --------------------------- | ------------------------------------------------- |
| Button height               | **40–48 px**; 44–48 px is a good default          |
| Minimum touch target        | **44 × 44 px**                                    |
| Icon touch area             | **44 × 44 px** even if the icon itself is smaller |
| Icon size                   | Usually **20–24 px**                              |
| Small icon                  | **16–20 px**                                      |
| Large icon                  | **28–32 px**                                      |
| Input field height          | **44–48 px**                                      |
| Checkbox / radio touch area | **44 × 44 px**                                    |
| Tab bar item                | Around **44–48 px** minimum touch area            |
| Bottom navigation           | Usually **56–80 px** total height                 |
| Top app bar                 | Usually **56 px**                                 |
| Standard spacing            | Use **4 px increments**                           |
| Common spacing              | **4, 8, 12, 16, 20, 24, 32, 40, 48 px**           |
| Screen side padding         | Usually **16–24 px**                              |
| Card padding                | Usually **16 px**                                 |
| Button horizontal padding   | Usually **16–24 px**                              |
| Text line height            | Generally **1.2–1.5× font size**                  |
| Body text                   | Usually **16 px**                                 |
| Secondary text              | Usually **14 px**                                 |
| Caption                     | Usually **12 px**                                 |
| Heading                     | ~**20–32 px** depending on hierarchy              |
| Corner radius               | Commonly **8, 12, 16 px**                         |
| Divider                     | **1 px**                                          |
| Avatar                      | Commonly **32, 40, 48 px**                        |
| List row                    | Usually **48–64 px**                              |

---

## 🎯 Touch Target ≠ Visual Size

An icon does not need to be visually 44×44 px.

For example:

- **Icon:** 24×24 px
- **Clickable/touch area:** 44×44 px minimum

The 44×44 area can be invisible; it represents the interactive area around the icon.

This is useful for icons such as:

- Back
- Close
- Menu
- Heart
- Bookmark
- Edit
- Delete
- Checkbox
- Radio button
- Navigation icons

---

# 📐 Spacing System

A useful approach is a **4-point / 8-point spacing system**.

| Spacing | Typical use          |
| ------: | -------------------- |
|    4 px | Very tight spacing   |
|    8 px | Small spacing        |
|   12 px | Small/medium spacing |
|   16 px | Standard spacing     |
|   20 px | Medium spacing       |
|   24 px | Large spacing        |
|   32 px | Section spacing      |
|   40 px | Major spacing        |
|   48 px | Very large spacing   |

### Example

```text
Title
↓ 8px
Subtitle
↓ 24px
Input
↓ 16px
Input
↓ 24px
Button
```

Avoid random spacing such as 13px, 17px, 21px, 27px unless there is a specific reason.

---

# 🔘 Buttons

Good default:

- **Height:** 48 px
- **Radius:** 8–12 px
- **Horizontal padding:** 16–24 px
- **Text:** 14–16 px

For compact interfaces, 40–44 px can work.

For comfortable mobile interaction, 48 px is a safe default.

---

# 🔤 Typography Hierarchy

Avoid using too many different font sizes.

A simple system:

|  Size | Typical use               |
| ----: | ------------------------- |
| 32 px | Large heading             |
| 24 px | Heading                   |
| 20 px | Section heading           |
| 16 px | Body text                 |
| 14 px | Secondary/small body text |
| 12 px | Caption                   |

The important thing is **visual hierarchy**, not exact numbers.

---

# 🧱 Layout

For a typical mobile screen:

- **Side padding:** 16–24 px
- **Default starting point:** 16 px

Example:

```text
┌─────────────────────────────┐
│ ←                           │
│                             │
│  16–24px side padding       │
│                             │
│  Title                      │
│                             │
│  Content                    │
│                             │
│  Content                    │
│                             │
│  [       BUTTON       ]     │
│                             │
└─────────────────────────────┘
```

---

# 🃏 Cards

Good defaults:

- **Padding:** 16 px
- **Radius:** 12–16 px
- **Gap between cards:** 12–16 px

Do not automatically put everything inside cards. Sometimes spacing and hierarchy are enough.

---

# 🧭 Navigation

## Top App Bar

Usually around:

**56 px height**

```text
┌─────────────────────────────┐
│ ←   Profile                 │
└─────────────────────────────┘
```

## Bottom Navigation

Usually around:

**56–80 px**

Each navigation item should have enough touch area—not just the icon itself.

---

# 🎨 Color System

Instead of choosing colors independently for every component, create a small design system:

- Primary
- Secondary
- Background
- Surface
- Text
- Secondary text
- Border
- Success
- Warning
- Error

Example:

```text
Primary       → brand/action
Background    → page
Surface       → cards/input
Text          → main content
Muted         → secondary information
Error         → destructive/error state
Success       → successful state
```

Always consider sufficient text/background contrast.

---

# 🧩 Component Consistency

Avoid inconsistent component sizes.

Bad:

```text
Button A → 48px
Button B → 44px
Button C → 51px
```

Better:

```text
Primary button → 48px
Secondary      → 48px
Input          → 48px
Icon button    → 44×44px
```

Establish rules and reuse them throughout the application.

This is especially useful when building reusable components in Laravel/Vue or other frontend frameworks.

---

# 🧠 Important UX Rules

## 1. Make Important Actions Obvious

Primary actions should visually stand out from secondary actions.

Example:

```text
Save Changes    ← Primary
Cancel          ← Secondary
```

---

## 2. Don't Make Users Guess

Instead of vague labels such as:

```text
Submit
```

use specific labels when appropriate:

```text
Create Account
Save Changes
Delete Account
Send Message
```

The user should understand what will happen when they tap it.

---

## 3. Give Feedback

After an action, provide feedback.

```text
Saving...
   ↓
Saved ✓
```

The user should not have to wonder whether the action worked.

---

## 4. Show Loading States

Do not leave the user staring at a frozen screen.

Examples:

```text
[ Saving... ]
```

or use a loading indicator/skeleton.

---

## 5. Design Different UI States

Do not design only the normal state.

Consider:

- Loading
- Empty
- Error
- Success
- Disabled
- Focused
- Pressed
- Hovered (for web)

For inputs, consider:

```text
Default
Focused
Filled
Error
Disabled
```

---

# ⭐ Most Important Numbers to Memorize

If you only memorize a few numbers, remember:

```text
44px   → Minimum touch target
48px   → Great default button/input height
24px   → Common icon size
16px   → Common body text
16px   → Common screen/card padding
8px    → Common small spacing
4px    → Spacing system base
56px   → Common top app bar
56–80px → Common bottom navigation
12–16px → Common card radius
```

---

# 🚀 Starter Formula for a New Mobile Screen

When designing a new screen, start with:

```text
Screen padding       → 16px
Spacing               → multiples of 4px
Button height         → 48px
Input height          → 48px
Icon                  → 24×24px
Icon touch area       → 44×44px
Body text             → 16px
Secondary text        → 14px
Card padding          → 16px
Card radius           → 12px
Top app bar           → 56px
```

These are **practical defaults, not absolute laws**. iOS and Android have their own platform conventions, and accessibility requirements can sometimes call for larger touch targets.
