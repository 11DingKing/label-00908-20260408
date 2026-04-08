<!DOCTYPE html>
<html lang="zh-CN">
<head><meta charset="UTF-8"><title>订阅到期提醒</title></head>
<body style="font-family: sans-serif; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2 style="color: #E6A23C;">订阅即将到期</h2>
    <p>{{ $user->name }}，您好：</p>
    <p>您的订阅即将到期，请注意续费：</p>
    <table style="width: 100%; border-collapse: collapse; margin: 16px 0;">
        <tr><td style="padding: 8px; border-bottom: 1px solid #eee; color: #909399;">订阅计划</td><td style="padding: 8px; border-bottom: 1px solid #eee;">{{ $plan->name }}</td></tr>
        <tr><td style="padding: 8px; border-bottom: 1px solid #eee; color: #909399;">到期时间</td><td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: bold; color: #E6A23C;">{{ $subscription->end_date?->format('Y-m-d') }}</td></tr>
        <tr><td style="padding: 8px; border-bottom: 1px solid #eee; color: #909399;">自动续费</td><td style="padding: 8px; border-bottom: 1px solid #eee;">{{ $subscription->auto_renew ? '已开启' : '未开启' }}</td></tr>
    </table>
    <p>如需继续使用服务，请及时续费或确认自动续费已开启。</p>
    <p style="color: #909399; font-size: 12px; margin-top: 30px;">此邮件由系统自动发送，请勿回复。</p>
</body>
</html>
