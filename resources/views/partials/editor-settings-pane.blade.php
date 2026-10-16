{{-- Desktop settings sidebar. A permanent column down the left of the
     editor: the first column of the editor root's grid, spanning the toolbar
     row and the workspace row, so it runs from the top of the page content
     to its foot beside whichever puzzle layout is active. It holds the
     shared settings form (editor-settings-form) with no heading of its own:
     the tab bar sits at the top, and Export and Save follow the active
     tab's fields inside the form rather than pinning to the pane's foot.
     Rendered from `lg` up only; below that the toolbar cog opens the modal
     (editor-settings-modal) instead.

     It sits flush against the page: negative margins pull it out through
     main's 2rem gutters on the start, top and bottom, so it butts up to the
     window edge and the header with no gap, and it takes the page colour
     rather than a card surface, with only a hairline on its content side.
     Because a stretched grid item grows by its negative margins, the pane
     is 2rem wider and 4rem taller than its grid area. The pane never pushes
     or overlays the layout: the grid track reserves its room up front, so
     the puzzle sizes itself to the room that is left. --}}
<aside
    aria-label="{{ __('Puzzle settings') }}"
    data-test="settings-pane"
    class="border-line hidden min-h-0 flex-col overflow-hidden border-e lg:col-start-1 lg:row-span-2 lg:row-start-1 lg:-ms-8 lg:-mt-8 lg:-mb-8 lg:flex"
>
    <div class="flex min-h-0 flex-1 flex-col px-4">
        @include('partials.editor-settings-form', ['settingsSurface' => 'pane'])
    </div>

</aside>
