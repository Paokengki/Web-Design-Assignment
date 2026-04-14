<?php

declare(strict_types=1);

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function old(string $name): string
{
    return escape($_POST[$name] ?? '');
}

function htmlAttributes(array $attributes): string
{
    $parts = [];

    foreach ($attributes as $key => $value) {
        $parts[] = sprintf('%s="%s"', escape($key), escape((string) $value));
    }

    return implode(' ', $parts);
}

function renderField(string $label, string $controlHtml, string $forId = ''): string
{
    $forAttribute = $forId !== '' ? ' for="' . escape($forId) . '"' : '';

    return <<<HTML
<div class="input-group">
    <label{$forAttribute}>{$label}</label>
    {$controlHtml}
</div>
HTML;
}

function renderTextInput(string $name, string $label, string $value = '', array $attributes = []): string
{
    $attributes = array_merge([
        'type' => 'text',
        'name' => $name,
        'id' => $name,
        'value' => $value,
    ], $attributes);

    return renderField($label, sprintf('<input %s>', htmlAttributes($attributes)), $name);
}

function renderEmailInput(string $name, string $label, string $value = '', array $attributes = []): string
{
    $attributes = array_merge([
        'type' => 'email',
        'name' => $name,
        'id' => $name,
        'value' => $value,
    ], $attributes);

    return renderField($label, sprintf('<input %s>', htmlAttributes($attributes)), $name);
}

function renderTextareaInput(string $name, string $label, string $value = '', array $attributes = []): string
{
    $attributes = array_merge([
        'name' => $name,
        'id' => $name,
        'rows' => 5,
    ], $attributes);

    return renderField($label, sprintf('<textarea %s>%s</textarea>', htmlAttributes($attributes), escape($value)), $name);
}

function renderSubmitButton(string $label, string $name, array $attributes = []): string
{
    $attributes = array_merge([
        'type' => 'submit',
        'name' => $name,
        'id' => $name,
    ], $attributes);

    return sprintf('<button %s>%s</button>', htmlAttributes($attributes), escape($label));
}

function renderStatusMessage(string $message = '', array $errors = []): string
{
    if (!empty($errors)) {
        $errorsHtml = '';
        foreach ($errors as $error) {
            $errorsHtml .= '<li>' . escape($error) . '</li>';
        }

        return <<<HTML
<div class="error-box" id="formErrorMessages">
    <ul>{$errorsHtml}</ul>
</div>
HTML;
    }

    if ($message !== '') {
        return <<<HTML
<div class="success-box" id="formSuccessMessage">
    <p>{$message}</p>
</div>
HTML;
    }

    return '<div id="formErrorMessages"></div>';
}
