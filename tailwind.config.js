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
                // OEMHub design tokens — always use these, never raw hex in templates
                navy: "#0B3A68", // Primary: headings, buttons, sidebar, hero bg
                amber: "#F59E0B", // Accent: CTAs, active states, progress bars, badge bg
                "amber-text": "#B45309", // Amber text on white/light bg — WCAG AA (never use amber on white)

                // Semantic grays
                body: "#334155", // Primary body text
                muted: "#64748B", // Secondary text, labels
                "bg-page": "#F8FAFC", // Page background
                "section-alt": "#EEF4FF", // Alternating section bg — navy 6% tint

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
                // Plus Jakarta Sans for display (H1-H3, logo, hero text)
                display: [
                    '"Plus Jakarta Sans"',
                    ...defaultTheme.fontFamily.sans,
                ],
                // Inter for body, labels, nav, descriptions
                sans: ["Inter", ...defaultTheme.fontFamily.sans],
                // JetBrains Mono for OEM numbers ONLY — every single one
                mono: ['"JetBrains Mono"', ...defaultTheme.fontFamily.mono],
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
