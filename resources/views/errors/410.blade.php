{{-- 410 Gone — a URL an admin deliberately retired. Same page as the 404 (noindex, links back to the shop) with its own
     wording; the HTTP status is what tells search engines to drop the address. --}}
@php $httpCode = 410; @endphp
@extends('errors.404')
