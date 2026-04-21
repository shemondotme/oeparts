## Summary

<!-- What does this PR do? One or two sentences. -->

## Type of change

- [ ] Bug fix
- [ ] New feature
- [ ] Refactor / code quality
- [ ] Documentation
- [ ] Other: ___

## Checklist

- [ ] `php artisan test` passes (all tests green)
- [ ] `php artisan view:cache` passes (no Blade errors)
- [ ] No float arithmetic on money (bcmath only)
- [ ] No `Cache::flush()` anywhere
- [ ] No `auth()->user()` inside admin controllers
- [ ] No hardcoded VAT rates / OTP settings / thresholds
- [ ] Mail dispatched via queue jobs, not `Mail::send()`
- [ ] OEM searches use `normalized_oem` column
- [ ] New migrations added as new files (no edits to existing ones)
- [ ] Tests use `#[Test]` attribute, not `/** @test */`
- [ ] New DB price columns are `DECIMAL(10,2)`, not `FLOAT`

## Related issues

Closes #
