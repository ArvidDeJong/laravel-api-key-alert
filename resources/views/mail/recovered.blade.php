<x-mail::message>
# {{ __('api-key-alert::mail.recovered.heading', ['service' => $service]) }}

{{ __('api-key-alert::mail.recovered.intro', ['service' => $service, 'app' => $context['app'] ?? '']) }}

@if ($failedSince)
{{ __('api-key-alert::mail.recovered.since', ['since' => $failedSince]) }}
@endif

@foreach ($context as $field => $value)
- **{{ trans()->has('api-key-alert::mail.fields.'.$field) ? __('api-key-alert::mail.fields.'.$field) : $field }}:** {{ is_scalar($value) ? $value : json_encode($value) }}
@endforeach
</x-mail::message>
