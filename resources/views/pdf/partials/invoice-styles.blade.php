    <style>
        /* Shared by every PDF document (order invoices, quotations, proformas, invoices, credit notes).
           dompdf has no flex/grid, so all layout here is tables. */
        @page {
            margin: 40px 40px 46px 40px;
        }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 11.5px;
            line-height: 1.5;
            color: #0A1228; /* Ink */
        }
        .mono {
            font-family: 'Courier New', Courier, monospace;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        td, th {
            vertical-align: top;
        }
        .text-right {
            text-align: right;
        }
        .label {
            font-size: 8.5px;
            font-weight: bold;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: #7A7360;
        }

        /* ── Brand band ── */
        .band {
            background-color: #0A1228; /* Ink */
            border-bottom: 4px solid #F59E0B; /* Amber */
        }
        .band td {
            padding: 20px 26px 18px 26px;
            vertical-align: bottom;
        }
        .band .wordmark {
            font-size: 26px;
            color: #F7F3E7; /* Ivory */
        }
        .wordmark .wordmark-heavy { font-weight: bold; }
        .wordmark .wordmark-light { font-weight: normal; }
        .wordmark .wordmark-dot { font-weight: bold; color: #F59E0B; }
        .band .eyebrow {
            font-family: 'Courier New', Courier, monospace;
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: #F59E0B;
        }
        .band .doc-title {
            font-size: 22px;
            font-weight: bold;
            letter-spacing: 0.05em;
            color: #F7F3E7;
        }

        /* ── Number / date strip ── */
        .strip {
            background-color: #FAF6EA;
            border-bottom: 1px solid #E6DFC6;
        }
        .strip td {
            padding: 10px 26px 9px 26px;
        }
        .strip .value {
            font-family: 'Courier New', Courier, monospace;
            font-size: 11.5px;
            font-weight: bold;
        }

        /* ── Body ── */
        .content {
            padding: 22px 6px 0 6px;
        }
        .party-spacer {
            width: 24px;
        }
        .party {
            border-left: 3px solid #F59E0B;
            padding-left: 12px;
            line-height: 1.55;
        }
        .party .label {
            margin-bottom: 3px;
        }
        .section {
            margin-bottom: 16px;
        }
        .section-title {
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.14em;
            color: #0A1228;
            border-bottom: 2px solid #0A1228;
            border-left: 3px solid #F59E0B;
            padding: 2px 0 5px 10px;
            margin-bottom: 10px;
        }
        table.items {
            margin-top: 20px;
        }
        table.items th {
            background-color: #0A1228;
            color: #F7F3E7;
            text-align: left;
            padding: 8px 9px;
            font-family: 'Courier New', Courier, monospace;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        table.items td {
            padding: 9px;
            border-bottom: 1px solid #E6DFC6;
            font-size: 11px;
        }
        .condition-tag {
            font-family: 'Courier New', Courier, monospace;
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            border: 1px solid #0A1228;
            padding: 1px 5px;
        }

        /* ── Notices + totals ── */
        .summary {
            margin-top: 18px;
        }
        .totals td {
            padding: 4px 0;
            font-size: 11.5px;
        }
        .totals .value {
            font-family: 'Courier New', Courier, monospace;
            text-align: right;
        }
        .totals .grand td {
            background-color: #0A1228;
            color: #F7F3E7;
            font-size: 14px;
            font-weight: bold;
            padding: 10px 12px;
        }
        .notice-box {
            margin-bottom: 8px;
            padding: 9px 12px;
            border: 1px solid #E6DFC6;
            border-left: 3px solid #F59E0B;
            background-color: #FAF6EA;
            font-size: 10.5px;
            line-height: 1.5;
        }

        /* ── Payment panel ── */
        .pay {
            margin-top: 20px;
            border: 1px solid #E6DFC6;
            page-break-inside: avoid;
        }
        .pay .pay-head td {
            padding: 9px 14px 6px 14px;
            font-size: 9.5px;
            font-weight: bold;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            border-bottom: 2px solid #0A1228;
        }
        .pay td {
            padding: 7px 14px;
        }
        .pay .value {
            font-family: 'Courier New', Courier, monospace;
            font-size: 11px;
        }
        .pay-note {
            padding: 0 14px 10px 14px;
            font-size: 9.5px;
            color: #6B7280;
        }

        /* ── Footer ── */
        .footer {
            margin-top: 24px;
            padding-top: 12px;
            border-top: 1px solid #E6DFC6;
            text-align: center;
            font-size: 9.5px;
            color: #7A7360;
        }
    </style>
