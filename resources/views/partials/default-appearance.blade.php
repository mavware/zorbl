{{-- Dark is the default appearance. Flux records "system" by clearing its
     `flux.appearance` key, which is also what a first visit looks like, so a
     separate `appearance.chosen` flag (set by the appearance settings page)
     tells the two apart: without it, a visitor with no stored preference is
     started on dark. Must run before @fluxAppearance, which applies the key. --}}
<script>
    try {
        if (! window.localStorage.getItem('flux.appearance') && ! window.localStorage.getItem('appearance.chosen')) {
            window.localStorage.setItem('flux.appearance', 'dark');
        }
    } catch (error) {
        document.documentElement.classList.add('dark');
    }
</script>
