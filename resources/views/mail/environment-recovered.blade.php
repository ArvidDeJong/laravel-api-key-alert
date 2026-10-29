<x-mail::message>
# {{ __('api-key-alert::mail.environment.recovered.heading', ['app' => $context['app'] ?? '']) }}

{{ __('api-key-alert::mail.environment.recovered.intro', ['app' => $context['app'] ?? '']) }}

@if ($failedSince)
{{ __('api-key-alert::mail.environment.recovered.since', ['since' => $failedSince]) }}
@endif

@foreach ($context as $field => $value)
- **{{ trans()->has('api-key-alert::mail.fields.'.$field) ? __('api-key-alert::mail.fields.'.$field) : $field }}:** {{ is_scalar($value) ? $value : json_encode($value) }}
@endforeach
</x-mail::message>
