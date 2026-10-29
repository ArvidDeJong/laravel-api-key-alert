<x-mail::message>
# {{ __('api-key-alert::mail.failed.heading', ['service' => $service]) }}

{{ __('api-key-alert::mail.failed.intro', ['service' => $service, 'app' => $context['app'] ?? '']) }}

<x-mail::panel>
{{ $reason }}
</x-mail::panel>

@foreach ($context as $field => $value)
- **{{ trans()->has('api-key-alert::mail.fields.'.$field) ? __('api-key-alert::mail.fields.'.$field) : $field }}:** {{ is_scalar($value) ? $value : json_encode($value) }}
@endforeach

**{{ __('api-key-alert::mail.failed.next_steps_heading') }}**

{{ __('api-key-alert::mail.failed.next_steps') }}

{{ trans_choice('api-key-alert::mail.failed.throttle', intdiv($throttleMinutes, 60) ?: 1, ['hours' => intdiv($throttleMinutes, 60) ?: 1]) }}
</x-mail::message>
