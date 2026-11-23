<div style="font-family: Arial, sans-serif; font-size: 13px; padding: 8px 12px; background: #f5f5f5; border-bottom: 1px solid #ddd;">
    <strong>Mail Class:</strong> {{ $email->mail_class ?? 'unknown' }}
</div>
{!!$email->content!!}
