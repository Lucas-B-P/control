# Design System Master File

> **LOGIC:** When building a specific page, first check `design-system/pages/[page-name].md`.
> If that file exists, its rules **override** this Master file.
> If not, strictly follow the rules below.

---

**Project:** Control
**Generated:** 2026-09-29 15:54:02
**Category:** Inventory & Stock Management
**Design Dials:** Density 8/10 (Dense / Dashboard)

---

## Global Rules

### Color Palette

| Role | Hex | CSS Variable |
|------|-----|--------------|
| Primary | `#0072C6` | `--color-primary` |
| Primary Hover | `#005A9E` | `--color-primary-hover` |
| On Primary | `#FFFFFF` | `--color-on-primary` |
| Secondary | `#475569` | `--color-secondary` |
| On Secondary | `#FFFFFF` | `--color-on-secondary` |
| Accent/CTA | `#0072C6` | `--color-accent` |
| On Accent/CTA | `#FFFFFF` | `--color-on-accent` |
| Background | `#F5F9FD` | `--color-background` |
| Foreground | `#0F172A` | `--color-foreground` |
| Card | `#FFFFFF` | `--color-card` |
| Card Foreground | `#0F172A` | `--color-card-foreground` |
| Muted | `#EEF4FA` | `--color-muted` |
| Muted Foreground | `#475569` | `--color-muted-foreground` |
| Border | `#DCE6F0` | `--color-border` |
| Destructive | `#DC2626` | `--color-destructive` |
| On Destructive | `#FFFFFF` | `--color-on-destructive` |
| Ring | `#0072C6` | `--color-ring` |

**Color Notes:** Identidade visual: azul `#0072C6` + branco. Branco sobre `#0072C6` tem contraste ~5:1 (passa 4.5:1). Logo em `public/assets/logo.png` (engrenagem com usuário, gradiente roxo/azul), sempre sobre fundo branco. Favicons: `favicon-32x32.png` e `apple-touch-icon.png`.

### Typography

- **Heading Font:** Figtree
- **Body Font:** Noto Sans
- **Mood:** medical, clean, accessible, professional, healthcare, trustworthy
- **Google Fonts:** [Figtree + Noto Sans](https://fonts.googleapis.com/css2?family=Figtree:wght@300;400;500;600;700&family=Noto+Sans:wght@300;400;500;700&display=swap)

**CSS Import:**
```css
@import url('https://fonts.googleapis.com/css2?family=Figtree:wght@300;400;500;600;700&family=Noto+Sans:wght@300;400;500;700&display=swap');
```

### Spacing Variables

*Density: 8/10 — Dense / Dashboard*

| Token | Value | Usage |
|-------|-------|-------|
| `--space-xs` | `2px` / `0.125rem` | Tight gaps |
| `--space-sm` | `4px` / `0.25rem` | Icon gaps, inline spacing |
| `--space-md` | `8px` / `0.5rem` | Standard padding |
| `--space-lg` | `12px` / `0.75rem` | Section padding |
| `--space-xl` | `16px` / `1rem` | Large gaps |
| `--space-2xl` | `24px` / `1.5rem` | Section margins |
| `--space-3xl` | `32px` / `2rem` | Hero padding |

### Shadow Depths

| Level | Value | Usage |
|-------|-------|-------|
| `--shadow-sm` | `0 1px 2px rgba(0,0,0,0.05)` | Subtle lift |
| `--shadow-md` | `0 4px 6px rgba(0,0,0,0.1)` | Cards, buttons |
| `--shadow-lg` | `0 10px 15px rgba(0,0,0,0.1)` | Modals, dropdowns |
| `--shadow-xl` | `0 20px 25px rgba(0,0,0,0.15)` | Hero images, featured cards |

---

## Component Specs

Flat style: no elevation shadows or hover lift on buttons/cards; use color/border changes only. Shadows are reserved for floating layers (dropdowns, modals).

### Buttons

```css
.btn-primary {
  background: #0072C6;
  color: #FFFFFF;
  height: 40px;
  padding: 0 16px;
  border-radius: 6px;
  font-weight: 600;
  transition: background-color 150ms ease;
  cursor: pointer;
}

.btn-primary:hover {
  background: #005A9E;
}

/* Secondary Button */
.btn-secondary {
  background: transparent;
  color: #0072C6;
  border: 1px solid #0072C6;
  height: 40px;
  padding: 0 16px;
  border-radius: 6px;
  font-weight: 600;
  transition: background-color 150ms ease;
  cursor: pointer;
}

.btn-secondary:hover {
  background: #F2F3F4;
}
```

### Cards

```css
.card {
  background: #FFFFFF;
  border: 1px solid #E6E8EA;
  border-radius: 8px;
  padding: 16px;
}

/* Only when the whole card is a link/action */
.card-interactive {
  cursor: pointer;
  transition: border-color 150ms ease;
}

.card-interactive:hover {
  border-color: #0072C6;
}
```

### Inputs

```css
.input {
  height: 40px;
  padding: 0 12px;
  border: 1px solid #CBD5E1;
  border-radius: 6px;
  font-size: 16px;
  transition: border-color 150ms ease;
}

.input:focus-visible {
  border-color: #0072C6;
  outline: 2px solid #0072C6;
  outline-offset: 2px;
}
```

### Modals

```css
.modal-overlay {
  background: rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(4px);
}

.modal {
  background: white;
  border-radius: 16px;
  padding: 32px;
  box-shadow: var(--shadow-xl);
  max-width: 500px;
  width: 90%;
}
```

---

## Style Guidelines

**Style:** Flat Design

**Keywords:** 2D, minimalist, bold colors, no shadows, clean lines, simple shapes, typography-focused, modern, icon-heavy

**Best For:** Web apps, mobile apps, cross-platform, startup MVPs, user-friendly, SaaS, dashboards, corporate

**Key Effects:** No gradients/shadows, simple hover (color/opacity shift), fast loading, clean transitions (150-200ms ease), minimal icons

### Page Pattern

Internal inventory app (not a marketing site): sidebar navigation + top bar, content area with page title, primary action (e.g. "Novo produto") at top right, filters above data tables, KPI cards (estoque baixo, sem estoque, movimentações do dia) on the dashboard.

Stock status must never rely on color alone — pair color with a text label or icon (e.g. "Em estoque", "Estoque baixo", "Sem estoque").

### Tailwind Guidelines (html-tailwind)

- **Inputs:** consistent size `h-10 w-full px-3`
- **Focus:** `focus-visible:ring-2 focus-visible:ring-offset-2`; never `outline-none` without a replacement
- **Long text in tables:** `truncate` or `line-clamp-*` so it doesn't break the layout
- **Badges/chips:** `flex flex-wrap gap-2`; single label `min-w-0 whitespace-nowrap truncate`, icon `shrink-0`
- **Images (product photos):** reserve space with `aspect-square` / explicit size to avoid layout shift

---

## Anti-Patterns (Do NOT Use)

- ❌ Excessive decoration
- ❌ Complex shadows
- ❌ 3D effects

### Additional Forbidden Patterns

- ❌ **Emojis as icons** — Use SVG icons (Heroicons, Lucide, Simple Icons)
- ❌ **Missing cursor:pointer** — All clickable elements must have cursor:pointer
- ❌ **Layout-shifting hovers** — Avoid scale transforms that shift layout
- ❌ **Low contrast text** — Maintain 4.5:1 minimum contrast ratio
- ❌ **Instant state changes** — Always use transitions (150-300ms)
- ❌ **Invisible focus states** — Focus states must be visible for a11y

---

## Pre-Delivery Checklist

Before delivering any UI code, verify:

- [ ] No emojis used as icons (use SVG instead)
- [ ] All icons from consistent icon set (Heroicons/Lucide)
- [ ] `cursor-pointer` on all clickable elements
- [ ] Hover states with smooth transitions (150-300ms)
- [ ] Light mode: text contrast 4.5:1 minimum
- [ ] Focus states visible for keyboard navigation
- [ ] `prefers-reduced-motion` respected
- [ ] Responsive: 375px, 768px, 1024px, 1440px
- [ ] No content hidden behind fixed navbars
- [ ] No horizontal scroll on mobile
