<x-mail::message>
# {{ __('api-key-alert::mail.environment.failed.heading', ['app' => $context['app'] ?? '']) }}

{{ __('api-key-alert::mail.environment.failed.intro', ['app' => $context['app'] ?? '']) }}

@foreach ($context['settings'] ?? [] as $key => $setting)
- **{{ $key }}:** {{ __('api-key-alert::mail.environment.failed.setting', ['actual' => json_encode($setting['actual'] ?? null), 'expected' => json_encode($setting['expected'] ?? null)]) }}
@endforeach

@foreach ($context as $field => $value)
@if ($field !== 'settings')
- **{{ trans()->has('api-key-alert::mail.fields.'.$field) ? __('api-key-alert::mail.fields.'.$field) : $field }}:** {{ is_scalar($value) ? $value : json_encode($value) }}
@endif
@endforeach

**{{ __('api-key-alert::mail.environment.failed.next_steps_heading') }}**

{{ __('api-key-alert::mail.environment.failed.next_steps') }}

{{ __('api-key-alert::mail.environment.failed.reminder') }}
</x-mail::message>
