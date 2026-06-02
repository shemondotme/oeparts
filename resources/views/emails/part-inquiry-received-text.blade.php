NEW PART INQUIRY — {{ config('app.name', 'OeParts') }}
================================================
Submitted: {{ $inquiry->created_at->format('d M Y, H:i') }} UTC

@if($inquiry->urgency === 'urgent')
⚡ URGENT — Please respond immediately
@elseif($inquiry->urgency === 'soon')
🕐 SOON — Please respond within a few hours
@endif

PART DETAILS
------------
OEM Number : {{ $inquiry->oem_number }}
Quantity   : {{ $inquiry->quantity }}
@if($inquiry->manufacturer || $inquiry->car_model || $inquiry->year)
Vehicle    : {{ implode(' ', array_filter([$inquiry->manufacturer, $inquiry->car_model, $inquiry->year])) }}
@endif
@if($inquiry->vin_number)
VIN        : {{ $inquiry->vin_number }}
@endif
@if($inquiry->notes)
Notes      : {{ $inquiry->notes }}
@endif

CUSTOMER CONTACT
----------------
Email : {{ $inquiry->email }}
@if($inquiry->phone)
Phone : {{ $inquiry->phone }}
@endif

Admin panel: {{ url('/admin/part-inquiries') }}
