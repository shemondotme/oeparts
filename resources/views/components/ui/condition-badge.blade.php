@props(['condition' => null])

@php
    $bg = '#6B7280';
    $text = '#FFFFFF';
    $label = '—';

    if ($condition instanceof \App\Models\Condition) {
        $bg = $condition->bg_color;
        $text = $condition->text_color;
        $label = condition_label($condition);
    } elseif (is_string($condition)) {
        $conditionModel = \App\Models\Condition::where('slug', $condition)->first();
        if ($conditionModel) {
            $bg = $conditionModel->bg_color;
            $text = $conditionModel->text_color;
            $label = condition_label($conditionModel);
        } else {
            $label = ucfirst($condition);
        }
    } elseif (is_object($condition) && property_exists($condition, 'bg_color')) {
        $bg = $condition->bg_color;
        $text = $condition->text_color;
        $label = $condition->name ?? 'Preview';
    }
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded px-2 py-0.5 bp-spec-mono font-bold']) }} style="background-color: {{ $bg }}; color: {{ $text }};">
    {{ $slot->isEmpty() ? $label : $slot }}
</span>
