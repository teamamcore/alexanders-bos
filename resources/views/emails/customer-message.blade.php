<!DOCTYPE html>
<html>
<body style="font-family: -apple-system, Segoe UI, Helvetica, Arial, sans-serif; color: #1f2937; line-height: 1.55; max-width: 560px; margin: 0 auto; padding: 24px;">
    <div style="font-size: 15px; white-space: pre-line;">{{ $body }}</div>
    <hr style="border: 0; border-top: 1px solid #e5e7eb; margin: 24px 0;">
    <div style="font-size: 12px; color: #6b7280;">{{ \App\Models\Tenant::current()?->name ?? config('app.name') }}</div>
</body>
</html>
