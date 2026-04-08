<!DOCTYPE html>
<html lang="zh-CN">
<head><meta charset="UTF-8"><title>支付成功</title></head>
<body style="font-family: sans-serif; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2 style="color: #67C23A;">支付成功</h2>
    <p>{{ $user->name }}，您好：</p>
    <p>您的支付已成功处理：</p>
    <table style="width: 100%; border-collapse: collapse; margin: 16px 0;">
        <tr><td style="padding: 8px; border-bottom: 1px solid #eee; color: #909399;">账单编号</td><td style="padding: 8px; border-bottom: 1px solid #eee;">{{ $bill?->bill_number ?? '-' }}</td></tr>
        <tr><td style="padding: 8px; border-bottom: 1px solid #eee; color: #909399;">支付金额</td><td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: bold; color: #67C23A;">¥{{ number_format($payment->amount, 2) }}</td></tr>
        <tr><td style="padding: 8px; border-bottom: 1px solid #eee; color: #909399;">支付方式</td><td style="padding: 8px; border-bottom: 1px solid #eee;">{{ $payment->payment_method }}</td></tr>
        <tr><td style="padding: 8px; border-bottom: 1px solid #eee; color: #909399;">交易号</td><td style="padding: 8px; border-bottom: 1px solid #eee;">{{ $payment->transaction_id ?? '-' }}</td></tr>
        <tr><td style="padding: 8px; border-bottom: 1px solid #eee; color: #909399;">支付时间</td><td style="padding: 8px; border-bottom: 1px solid #eee;">{{ $payment->paid_at?->format('Y-m-d H:i:s') }}</td></tr>
    </table>
    <p style="color: #909399; font-size: 12px; margin-top: 30px;">此邮件由系统自动发送，请勿回复。</p>
</body>
</html>
