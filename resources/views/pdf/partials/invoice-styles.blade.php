    <style>
        @page {
            margin: 50px;
        }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: #0A1228; /* Ink */
        }
        .mono {
            font-family: 'Courier New', Courier, monospace;
        }

        /* ── Header: Ink band + Amber accent — same treatment as emails/layout.blade.php ── */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            background-color: #0A1228; /* Ink */
            border-bottom: 4px solid #F59E0B; /* Amber */
            padding: 18px 24px;
            margin: 0 0 24px 0;
        }
        .company-info h1 {
            font-size: 22px;
            margin: 0 0 8px 0;
            color: #F7F3E7; /* Ivory */
        }
        .company-info h1 .wordmark-heavy {
            font-weight: bold;
        }
        .company-info h1 .wordmark-light {
            font-weight: normal;
            opacity: 0.75;
        }
        .company-info h1 .wordmark-dot {
            font-weight: bold;
            color: #F59E0B; /* Amber */
        }
        .company-info div {
            color: #F7F3E7;
            opacity: 0.8;
            font-size: 11px;
            line-height: 17px;
        }
        .invoice-info {
            text-align: right;
        }
        .invoice-info .doc-eyebrow {
            font-family: 'Courier New', Courier, monospace;
            font-size: 10px;
            font-weight: bold;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: #F59E0B; /* Amber */
            margin: 0 0 8px 0;
        }
        .invoice-info h2 {
            font-size: 20px;
            font-weight: bold;
            letter-spacing: 0.04em;
            color: #F7F3E7; /* Ivory */
            margin: 0 0 12px 0;
        }
        .invoice-info .meta-row {
            font-size: 11px;
            color: #F7F3E7;
            margin-bottom: 3px;
        }
        .invoice-info .meta-row .label {
            display: inline-block;
            min-width: 60px;
            text-transform: uppercase;
            font-size: 9px;
            letter-spacing: 0.1em;
            opacity: 0.65;
            margin-right: 6px;
        }
        .invoice-info .meta-row .value {
            font-family: 'Courier New', Courier, monospace;
            font-weight: bold;
        }

        /* ── Sections ── */
        .section {
            margin-bottom: 22px;
        }
        .section-title {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.14em;
            color: #0A1228; /* Ink */
            border-bottom: 2px solid #0A1228;
            border-left: 3px solid #F59E0B; /* Amber accent tick, matches storefront section markers */
            padding: 2px 0 5px 10px;
            margin-bottom: 12px;
        }
        .two-columns {
            display: flex;
            justify-content: space-between;
        }
        .column {
            width: 48%;
            font-size: 12px;
            line-height: 19px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 2px;
        }
        th {
            background-color: #0A1228; /* Ink */
            color: #F7F3E7; /* Ivory */
            text-align: left;
            padding: 9px 10px;
            border: 1px solid #0A1228;
            font-family: 'Courier New', Courier, monospace;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        td {
            padding: 9px 10px;
            border: 1px solid #D8CFB6; /* Rule */
            font-size: 11.5px;
            vertical-align: top;
        }
        .text-right {
            text-align: right;
        }
        .condition-tag {
            display: inline-block;
            font-family: 'Courier New', Courier, monospace;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            border: 1px solid #0A1228;
            padding: 2px 6px;
        }
        .totals {
            width: 300px;
            margin-left: auto;
            margin-top: 18px;
        }
        .totals-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            font-size: 12px;
        }
        .totals-row .value {
            font-family: 'Courier New', Courier, monospace;
        }
        .totals-row.total {
            font-weight: bold;
            font-size: 15px;
            color: #0A1228;
            border-top: 2px solid #0A1228;
            padding-top: 10px;
            margin-top: 10px;
        }
        .notice-box {
            margin-top: 14px;
            padding: 10px 14px;
            border: 1px solid #D8CFB6; /* Rule */
            border-left: 3px solid #F59E0B; /* Amber */
            background-color: #FAF6EA; /* faint ivory tint — bounded, print-safe */
            font-size: 11px;
            line-height: 17px;
        }
        .footer {
            margin-top: 28px;
            padding-top: 14px;
            border-top: 1px solid #D8CFB6;
            text-align: center;
            font-size: 10px;
            color: #6B7280;
        }
    </style>
