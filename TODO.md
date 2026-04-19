# TODO

Checklist for automated and manual follow-up work. The automation script
scans this file for `- [ ]` items and flips them to `- [x]` when done.

## Open

- [x] Consider scheduled publishing support on Contest (add `publish_at` column, include in `published()` scope, scheduler command to flip draft→upcoming)
- [ ] Consider adding scheduled status transitions for active→ended (the `contests:process-ended` command exists but could benefit from more test coverage)
- [ ] Add Filament admin action to bulk-schedule publish dates on draft contests
- [ ] In the editor, when you have a cell selected, then click away from the grid, then click back on the cell, the direction of the selection changes.  To make it more clear what is going on, when the user clicks away from the grid (not on a cell or in the clue area), the grid should lose focus and no cell should be selected.

## Done

<!-- completed items move here, preserving their `- [x]` state -->
