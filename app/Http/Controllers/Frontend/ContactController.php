<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Http\Requests\Frontend\ContactFormRequest;
use App\Models\ContactMessage;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    /**
     * Display contact form page.
     */
    public function show(string $lang)
    {
        return view('frontend.contact.show');
    }

    /**
     * Submit contact form.
     */
    public function submit(ContactFormRequest $request)
    {
        ContactMessage::create([
            'email' => $request->email,
            'name' => $request->name,
            'subject_type' => $request->subject_type,
            'order_number' => $request->order_number,
            'oem_number' => $request->oem_number,
            'manufacturer' => $request->manufacturer,
            'car_model' => $request->car_model,
            'year' => $request->year,
            'vin_number' => $request->vin_number,
            'message' => $request->message,
            'status' => 'unread',
            'otp_verified' => true,
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['success' => true, 'message' => 'Your message has been sent successfully. We will get back to you soon.']);
    }
}
