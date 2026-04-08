<!DOCTYPE html>
<html lang="zh-CN">
<head><meta charset="UTF-8"><title>账单通知</title></head>
<body style="font-family: sans-serif; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2 style="color: #409EFF;">账单通知</h2>
    <p>{{ $user->name }}，您好：</p>
    <p>您有一笔新的账单需要处理：</p>
    <table style="width: 100%; border-collapse: collapse; margin: 16px 0;">
        <tr><td style="padding: 8px; border-bottom: 1px solid #eee; color: #909399;">账单编号</td><td style="padding: 8px; border-bottom: 1px solid #eee;">{{ $bill->bill_number }}</td></tr>
        <tr><td style="padding: 8px; border-bottom: 1px solid #eee; color: #909399;">账期</td><td style="padding: 8px; border-bottom: 1px solid #eee;">{{ $bill->period_start?->format('Y-m-d') }} 至 {{ $bill->period_end?->format('Y-m-d') }}</td></tr>
        <tr><td style="padding: 8px; border-bottom: 1px solid #eee; color: #909399;">总金额</td><td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: bold; color: #409EFF;">¥{{ number_format($bill->total_amount, 2) }}</td></tr>
        <tr><td style="padding: 8px; border-bottom: 1px solid #eee; color: #909399;">到期日</td><td style="padding: 8px; border-bottom: 1px solid #eee;">{{ $bill->due_date?->format('Y-m-d') }}</td></tr>
    </table>
    <p>请在到期日前完成支付，谢谢。</p>
    <p style="color: #909399; font-size: 12px; margin-top: 30px;">此邮件由系统自动发送，请勿回复。</p>
</body>
</html>
