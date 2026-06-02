import defaultTheme from "tailwindcss/defaultTheme";

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        "./vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php",
        "./storage/framework/views/*.php",
        "./resources/views/**/*.blade.php",
        "./resources/js/**/*.js",
    ],

    theme: {
        extend: {
            colors: {
                // OeParts design tokens — always use these, never raw hex in templates
                navy: "#0B3A68", // Primary: headings, buttons, sidebar, hero bg
                amber: "#F59E0B", // Accent: CTAs, active states, progress bars, badge bg
                "amber-text": "#B45309", // Amber text on white/light bg — WCAG AA (never use amber on white)

                // Admin UI accent — indigo family (premium SaaS; use via bp-* / layout tokens)
                brand: {
                    50: "#eef2ff",
                    100: "#e0e7ff",
                    200: "#c7d2fe",
                    300: "#a5b4fc",
                    400: "#818cf8",
                    500: "#6366f1",
                    600: "#4f46e5",
                    700: "#4338ca",
                    800: "#3730a3",
                    900: "#312e81",
                },

                // Semantic grays
                body: "#334155", // Primary body text
                muted: "#64748B", // Secondary text, labels
                "bg-page": "#F8FAFC", // Page background
                "section-alt": "#EEF4FF", // Alternating section bg — navy 6% tint

                // Admin shell surfaces (cool neutral — executive dashboard canvas)
                "admin-canvas": "#f1f4f9",
                "admin-surface": "#ffffff",
                "admin-border": "rgba(15, 23, 42, 0.08)",
                "admin-muted": "#64748b",

                // ── Industrial Blueprint tokens ───────────────────────
                // Deep ink for primary text on ivory — more contrast than navy
                ink: "#0A1228",
                // Secondary ink — for muted text on ivory surfaces
                "ink-muted": "#4E5A74",
                // Warm cream page background — evokes blueprint / spec-sheet paper
                ivory: "#F7F3E7",
                // Darker cream for alternating strips
                "ivory-alt": "#EFE9D6",
                // Paper white — card surfaces on ivory
                paper: "#FFFFFF",
                // Hairline rule colors (borders without shadows)
                rule: "#D8CFB6", // default hairline on ivory (warm)
                "rule-strong": "#B8AE90", // emphasized hairline
                "rule-dark": "#1D2A44", // hairline on navy/dark surfaces
                // Blueprint amber — slightly desaturated for ivory surfaces
                "amber-ink": "#9A5A00", // maximal contrast amber text (> AA) on ivory

                // Condition badge colors (bg / text pairs)
                "condition-new-bg": "#DCFCE7",
                "condition-new-text": "#16A34A",
                "condition-used-a-bg": "#DBEAFE",
                "condition-used-a-text": "#1D4ED8",
                "condition-used-b-bg": "#FEF3C7",
                "condition-used-b-text": "#D97706",
                "condition-used-c-bg": "#F1F5F9",
                "condition-used-c-text": "#64748B",
                "condition-remanufactured-bg": "#F3E8FF",
                "condition-remanufactured-text": "#7C3AED",
                "condition-aftermarket-bg": "#FEE2E2",
                "condition-aftermarket-text": "#DC2626",
                "condition-nos-bg": "#ECFDF5",
                "condition-nos-text": "#059669",
            },

            fontFamily: {
                // Plus Jakarta Sans — display / marketing headings (secondary to UI sans)
                display: [
                    '"Plus Jakarta Sans"',
                    '"Geist Sans"',
                    ...defaultTheme.fontFamily.sans,
                ],
                // Geist Sans — primary UI (navigation, body, forms) — 2026 SaaS standard
                sans: ['"Geist Sans"', 'ui-sans-serif', 'system-ui', ...defaultTheme.fontFamily.sans],
                // Geist Mono — metrics, tables, codes; JetBrains fallback for glyph coverage
                mono: ['"Geist Mono"', '"JetBrains Mono"', ...defaultTheme.fontFamily.mono],
            },

            fontSize: {
                // Admin type scale — scan-friendly hierarchy
                "admin-xs": ["0.6875rem", { lineHeight: "1rem", letterSpacing: "0.02em" }],
                "admin-sm": ["0.8125rem", { lineHeight: "1.25rem", letterSpacing: "0.01em" }],
                "admin-base": ["0.9375rem", { lineHeight: "1.5rem", letterSpacing: "0" }],
                // Industrial Blueprint display scale — tight, confident, technical
                "blueprint-xl": [
                    "clamp(3rem, 11vw, 9.5rem)",
                    { lineHeight: "0.88", letterSpacing: "-0.045em" },
                ],
                "blueprint-lg": [
                    "clamp(2.25rem, 7vw, 6rem)",
                    { lineHeight: "0.92", letterSpacing: "-0.03em" },
                ],
                "blueprint-md": [
                    "clamp(1.75rem, 4.5vw, 3.5rem)",
                    { lineHeight: "1.0", letterSpacing: "-0.02em" },
                ],
                // Small-caps meta label sizes
                "spec-xs": ["10px", { lineHeight: "1.2", letterSpacing: "0.22em" }],
                "spec-sm": ["11px", { lineHeight: "1.3", letterSpacing: "0.20em" }],
            },

            backgroundImage: {
                // Blueprint grid textures — used on ivory / navy surfaces
                "grid-ivory":
                    "linear-gradient(to right, rgba(10,18,40,0.045) 1px, transparent 1px), linear-gradient(to bottom, rgba(10,18,40,0.045) 1px, transparent 1px)",
                "grid-ivory-fine":
                    "linear-gradient(to right, rgba(10,18,40,0.025) 1px, transparent 1px), linear-gradient(to bottom, rgba(10,18,40,0.025) 1px, transparent 1px)",
                "grid-navy":
                    "linear-gradient(to right, rgba(255,255,255,0.04) 1px, transparent 1px), linear-gradient(to bottom, rgba(255,255,255,0.04) 1px, transparent 1px)",
                "grid-navy-fine":
                    "linear-gradient(to right, rgba(255,255,255,0.025) 1px, transparent 1px), linear-gradient(to bottom, rgba(255,255,255,0.025) 1px, transparent 1px)",
                // Dotted leader — for spec lists (label ........... value)
                "dotted-leader":
                    "radial-gradient(circle, rgba(10,18,40,0.3) 1px, transparent 1.5px)",
            },

            backgroundSize: {
                "grid-sm": "16px 16px",
                "grid-md": "24px 24px",
                "grid-lg": "48px 48px",
                "grid-xl": "96px 96px",
                leader: "6px 1px",
            },

            boxShadow: {
                "admin-card":
                    "0 1px 2px 0 rgb(15 23 42 / 0.04), 0 12px 40px -16px rgb(15 23 42 / 0.1), inset 0 1px 0 0 rgb(255 255 255 / 0.75)",
                "admin-card-hover":
                    "0 4px 12px -2px rgb(15 23 42 / 0.08), 0 20px 48px -20px rgb(79 70 229 / 0.12)",
                "admin-inset-highlight": "inset 0 1px 0 0 rgb(255 255 255 / 0.06)",
                "admin-sidebar": "8px 0 48px -16px rgb(0 0 0 / 0.4)",
            },

            animation: {
                // Blob animation for gradient mesh background
                blob: "blob 7s infinite",
                // Fade in up animation
                "fade-in-up": "fadeInUp 0.7s ease-out forwards",
                // Marquee animation for infinite carousel
                marquee: "marquee 30s linear infinite",
                // Gradient shift for CTA cards
                "gradient-shift": "gradient-shift 8s ease infinite",
                // Pulse glow for badges/icons
                "pulse-glow": "pulse-glow 2s ease-in-out infinite",
                // Scale bounce for button clicks
                "scale-bounce": "scale-bounce 0.3s ease-out",
                // Shimmer for loading states
                shimmer: "shimmer 1.5s infinite",
            },

            keyframes: {
                blob: {
                    "0%": { transform: "translate(0px, 0px) scale(1)" },
                    "33%": { transform: "translate(30px, -50px) scale(1.1)" },
                    "66%": { transform: "translate(-20px, 20px) scale(0.9)" },
                    "100%": { transform: "translate(0px, 0px) scale(1)" },
                },
                fadeInUp: {
                    "0%": {
                        opacity: "0",
                        transform: "translateY(20px)",
                    },
                    "100%": {
                        opacity: "1",
                        transform: "translateY(0)",
                    },
                },
                marquee: {
                    "0%": { transform: "translateX(0)" },
                    "100%": { transform: "translateX(-50%)" },
                },
                "gradient-shift": {
                    "0%, 100%": { backgroundPosition: "0% 50%" },
                    "50%": { backgroundPosition: "100% 50%" },
                },
                "pulse-glow": {
                    "0%, 100%": {
                        boxShadow: "0 0 0 0 rgba(245, 158, 11, 0.4)",
                    },
                    "50%": { boxShadow: "0 0 0 8px rgba(245, 158, 11, 0)" },
                },
                "scale-bounce": {
                    "0%": { transform: "scale(1)" },
                    "30%": { transform: "scale(0.95)" },
                    "60%": { transform: "scale(1.02)" },
                    "100%": { transform: "scale(1)" },
                },
                shimmer: {
                    "0%": { backgroundPosition: "-200% 0" },
                    "100%": { backgroundPosition: "200% 0" },
                },
            },
        },
    },

    plugins: [
        require("@tailwindcss/forms"),
        require("@tailwindcss/typography"),
    ],
};
