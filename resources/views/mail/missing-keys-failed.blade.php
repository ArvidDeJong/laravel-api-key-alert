<x-mail::message>
# {{ __('api-key-alert::mail.missing_keys.failed.heading', ['app' => $context['app'] ?? '']) }}

{{ __('api-key-alert::mail.missing_keys.failed.intro', ['app' => $context['app'] ?? '']) }}

@foreach ($context['keys'] ?? [] as $key)
- **{{ $key }}**
@endforeach

@foreach ($context as $field => $value)
@if ($field !== 'keys')
- **{{ trans()->has('api-key-alert::mail.fields.'.$field) ? __('api-key-alert::mail.fields.'.$field) : $field }}:** {{ is_scalar($value) ? $value : json_encode($value) }}
@endif
@endforeach

**{{ __('api-key-alert::mail.missing_keys.failed.next_steps_heading') }}**

{{ __('api-key-alert::mail.missing_keys.failed.next_steps') }}

{{ __('api-key-alert::mail.missing_keys.failed.reminder') }}
</x-mail::message>
