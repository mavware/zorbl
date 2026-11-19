# Gemini / Antigravity Pest Test Command

Run and verify tests cleanly using Pest.

## Usage Guidelines

1. **Targeted Run**:
   - Always run the narrowest relevant test path first:
     ```bash
     php artisan test --compact tests/Feature/YourTest.php
     ```
   - Filter by specific test method or description:
     ```bash
     vendor/bin/pest tests/Feature/YourTest.php --filter="your test description"
     ```

2. **Creating New Tests**:
   - Feature test:
     ```bash
     php artisan make:test --pest YourFeatureTest
     ```
   - Unit test:
     ```bash
     php artisan make:test --unit --pest YourUnitTest
     ```
   - Never prefix with directory name (`Feature/` or `Unit/`); pass the class name directly.

3. **Parallel Testing (Pre-push Check)**:
   - Run parallel tests when validating a whole module:
     ```bash
     vendor/bin/pest --parallel --compact tests/Feature/
     ```

4. **Rules**:
   - Read the `pest-testing` skill in `.agents/skills/pest-testing/` before writing complex datasets or Livewire tests.
   - Do NOT delete existing tests without explicit approval.
