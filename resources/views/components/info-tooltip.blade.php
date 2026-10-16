{{-- Info-circle tooltip for a field label or section heading. Place it inline
     right after the label text (inside a <span> for a Flux label, which is
     inline-flex, so the icon follows the last word even when the text
     wraps); the slot is the explanation that would otherwise sit under the
     field as a description. Toggleable so it opens on tap as well as hover;
     the negative vertical margins stop the button from making the label row
     taller.

     The click must not bubble to the label: Flux's label forwards clicks to
     its control, which would toggle a switch or checkbox whose explanation
     was being opened. The tooltip's own trigger listener sits on the button
     itself, so it still fires. --}}
<flux:tooltip toggleable position="top" align="start">
    <flux:button
        icon="information-circle"
        size="xs"
        variant="subtle"
        class="-my-1 ms-0.5 inline-flex align-middle text-fg-subtle hover:text-fg"
        x-on:click.stop
        :aria-label="__('More information')"
        data-test="info-tooltip"
    />

    <flux:tooltip.content class="max-w-72 text-sm font-normal whitespace-normal">
        {{ $slot }}
    </flux:tooltip.content>
</flux:tooltip>
