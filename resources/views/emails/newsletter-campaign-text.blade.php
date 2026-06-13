Hello!

{!! strip_tags($campaign->html_content) !!}

---
You are receiving this because you subscribed to our newsletter.
To unsubscribe, please visit: {{ url('/unsubscribe?email=' . urlencode($recipient->email)) }}
