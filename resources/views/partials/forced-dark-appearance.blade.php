{{--
    Temporary stand-in for @fluxAppearance: dark mode is enforced for now.
    Flux still calls window.Flux.applyAppearance(), so it stays defined but
    always applies dark, ignoring the stored preference and the OS setting.
    The saved `flux.appearance` value is left untouched so it takes effect
    again once this is swapped back to @fluxAppearance.
--}}
<style>
    :root.dark {
        color-scheme: dark;
    }
</style>
<script>
    window.Flux = {
        applyAppearance() {
            document.documentElement.classList.add('dark')
        },
    }

    window.Flux.applyAppearance()
</script>
